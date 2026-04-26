<?php

if (! defined('ABSPATH')) {
    exit;
}

class SSE_Sitemap
{
    const MAX_URLS = 50000;

    /** @var SSE_Storage */
    private $storage;

    public function __construct($storage)
    {
        $this->storage = $storage;
    }

    public function get_max_urls()
    {
        return self::MAX_URLS;
    }

    public function build_xml($urls)
    {
        return $this->build_urlset_xml($this->prepare_urls($urls));
    }

    public function prepare_urls($urls)
    {
        if (! is_array($urls)) {
            return array();
        }

        $unique = array();

        foreach ($urls as $item) {
            if (! is_array($item) || empty($item['loc']) || ! is_string($item['loc'])) {
                continue;
            }

            $loc = trim($item['loc']);
            if (! wp_http_validate_url($loc)) {
                continue;
            }

            $loc = strtok($loc, '?');
            $loc = strtok($loc, '#');

            if (! isset($unique[$loc])) {
                $unique[$loc] = array('loc' => $loc);
            }

            if (! empty($item['lastmod']) && is_string($item['lastmod'])) {
                $unique[$loc]['lastmod'] = $item['lastmod'];
            }
        }

        ksort($unique, SORT_STRING);

        return array_values($unique);
    }

    public function build_payload($urls)
    {
        $prepared = $this->prepare_urls($urls);
        $chunks = array_chunk($prepared, self::MAX_URLS);

        if (empty($chunks)) {
            $chunks = array(array());
        }

        $parts = array();

        foreach ($chunks as $index => $chunk) {
            $part_number = $index + 1;
            $part_name = sprintf('sitemap-%d.xml', $part_number);

            $parts[$part_name] = array(
                'xml' => $this->build_urlset_xml($chunk),
                'loc' => home_url('/' . $part_name),
            );
        }

        if (1 === count($parts)) {
            $single = reset($parts);

            return array(
                'index_xml' => $single['xml'],
                'parts'     => $parts,
                'is_index'  => false,
                'total_urls'=> count($prepared),
            );
        }

        $index_xml = $this->build_sitemap_index_xml($parts);

        return array(
            'index_xml' => $index_xml,
            'parts'     => $parts,
            'is_index'  => true,
            'total_urls'=> count($prepared),
        );
    }

    public function persist_payload($payload)
    {
        $index_xml = isset($payload['index_xml']) ? (string) $payload['index_xml'] : '';
        $parts = isset($payload['parts']) && is_array($payload['parts']) ? $payload['parts'] : array();

        $this->storage->save_xml($index_xml);
        $this->storage->save_parts($parts);

        $path = $this->get_sitemap_file_path('sitemap.xml');
        if (! $path || ! is_writable(dirname($path))) {
            return false;
        }

        $written = @file_put_contents($path, $index_xml, LOCK_EX);
        if (false === $written) {
            return false;
        }

        foreach ($parts as $filename => $part) {
            if ('sitemap.xml' === $filename) {
                continue;
            }

            $part_path = $this->get_sitemap_file_path($filename);
            if (! $part_path) {
                continue;
            }

            @file_put_contents($part_path, isset($part['xml']) ? (string) $part['xml'] : '', LOCK_EX);
        }

        return true;
    }

    public function maybe_render_public_sitemap()
    {
        if ('1' !== get_query_var('sse_sitemap')) {
            return;
        }

        $part_number = absint(get_query_var('sse_sitemap_part'));
        $filename = $part_number > 0 ? sprintf('sitemap-%d.xml', $part_number) : 'sitemap.xml';

        $path = $this->get_sitemap_file_path($filename);
        if ($path && file_exists($path) && is_readable($path)) {
            status_header(200);
            header('Content-Type: application/xml; charset=UTF-8');
            readfile($path);
            exit;
        }

        if ('sitemap.xml' === $filename) {
            $xml = $this->storage->get_xml();
            if ('' === $xml) {
                $payload = $this->build_payload($this->storage->get_urls());
                $xml = $payload['index_xml'];
            }

            status_header(200);
            header('Content-Type: application/xml; charset=UTF-8');
            echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $parts = $this->storage->get_parts();
        if (! empty($parts[$filename]['xml'])) {
            status_header(200);
            header('Content-Type: application/xml; charset=UTF-8');
            echo $parts[$filename]['xml']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        status_header(404);
        exit;
    }

    public function get_sitemap_file_path($filename)
    {
        if (! defined('ABSPATH')) {
            return '';
        }

        return trailingslashit(ABSPATH) . ltrim($filename, '/');
    }

    private function build_urlset_xml($urls)
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $urlset = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $doc->appendChild($urlset);

        foreach ($urls as $item) {
            $url_node = $doc->createElement('url');
            $loc_node = $doc->createElement('loc');
            $loc_node->appendChild($doc->createTextNode($item['loc']));
            $url_node->appendChild($loc_node);

            if (! empty($item['lastmod']) && is_string($item['lastmod'])) {
                $lastmod_node = $doc->createElement('lastmod');
                $lastmod_node->appendChild($doc->createTextNode($item['lastmod']));
                $url_node->appendChild($lastmod_node);
            }

            $urlset->appendChild($url_node);
        }

        return $doc->saveXML();
    }

    private function build_sitemap_index_xml($parts)
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $sitemap_index = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'sitemapindex');
        $doc->appendChild($sitemap_index);

        foreach ($parts as $part) {
            $node = $doc->createElement('sitemap');
            $loc = $doc->createElement('loc');
            $loc->appendChild($doc->createTextNode($part['loc']));
            $node->appendChild($loc);
            $sitemap_index->appendChild($node);
        }

        return $doc->saveXML();
    }
}
