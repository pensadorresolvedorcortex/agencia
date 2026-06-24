<?php

if (! defined('ABSPATH')) {
    exit;
}

class SSE_Storage
{
    const OPTION_URLS = 'sse_scanned_urls';
    const OPTION_XML = 'sse_sitemap_xml';
    const OPTION_UPDATED_AT = 'sse_last_updated_at';

    public function save_urls($urls)
    {
        update_option(self::OPTION_URLS, $urls, false);
        update_option(self::OPTION_UPDATED_AT, gmdate('c'), false);
    }

    public function get_urls()
    {
        $urls = get_option(self::OPTION_URLS, array());

        return is_array($urls) ? $urls : array();
    }

    public function save_xml($xml)
    {
        update_option(self::OPTION_XML, $xml, false);
        update_option(self::OPTION_UPDATED_AT, gmdate('c'), false);
    }

    public function get_xml()
    {
        $xml = get_option(self::OPTION_XML, '');

        return is_string($xml) ? $xml : '';
    }

    public function get_last_updated_at()
    {
        $value = get_option(self::OPTION_UPDATED_AT, '');

        return is_string($value) ? $value : '';
    }
}
