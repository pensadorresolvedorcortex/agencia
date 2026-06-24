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
        $prepared_urls = $this->prepare_urls($urls);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $urlset = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $doc->appendChild($urlset);

        foreach ($prepared_urls as $item) {
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

        return array_slice(array_values($unique), 0, self::MAX_URLS);
    }

    public function persist_xml($xml)
    {
        $this->storage->save_xml($xml);

        $path = $this->get_sitemap_file_path();
        if (! $path) {
            return false;
        }

        if (! is_writable(dirname($path))) {
            return false;
        }

        $written = @file_put_contents($path, $xml, LOCK_EX);

        return false !== $written;
    }

    public function maybe_render_public_sitemap()
    {
        if ('1' !== get_query_var('sse_sitemap')) {
            return;
        }

        $path = $this->get_sitemap_file_path();
        if ($path && file_exists($path) && is_readable($path)) {
            status_header(200);
            header('Content-Type: application/xml; charset=UTF-8');
            readfile($path);
            exit;
        }

        $xml = $this->storage->get_xml();
        if ('' === $xml) {
            $xml = $this->build_xml($this->storage->get_urls());
        }

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function get_sitemap_file_path()
    {
        if (! defined('ABSPATH')) {
            return '';
        }

        return trailingslashit(ABSPATH) . 'sitemap.xml';
    }
}
