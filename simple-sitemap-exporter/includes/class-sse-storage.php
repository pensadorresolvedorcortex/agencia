<?php

if (! defined('ABSPATH')) {
    exit;
}

class SSE_Storage
{
    const OPTION_URLS = 'sse_scanned_urls';
    const OPTION_XML = 'sse_sitemap_xml';
    const OPTION_PARTS = 'sse_sitemap_parts';
    const OPTION_UPDATED_AT = 'sse_last_updated_at';

    const OPTION_SCAN_STATE = 'sse_scan_state';
    const OPTION_SCAN_ITEMS = 'sse_scan_items';

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

    public function save_parts($parts)
    {
        update_option(self::OPTION_PARTS, $parts, false);
        update_option(self::OPTION_UPDATED_AT, gmdate('c'), false);
    }

    public function get_parts()
    {
        $parts = get_option(self::OPTION_PARTS, array());

        return is_array($parts) ? $parts : array();
    }


    public function save_scan_state($state)
    {
        update_option(self::OPTION_SCAN_STATE, $state, false);
    }

    public function get_scan_state()
    {
        $state = get_option(self::OPTION_SCAN_STATE, array());

        return is_array($state) ? $state : array();
    }

    public function clear_scan_state()
    {
        delete_option(self::OPTION_SCAN_STATE);
    }

    public function save_scan_items($items)
    {
        update_option(self::OPTION_SCAN_ITEMS, $items, false);
    }

    public function get_scan_items()
    {
        $items = get_option(self::OPTION_SCAN_ITEMS, array());

        return is_array($items) ? $items : array();
    }

    public function clear_scan_items()
    {
        delete_option(self::OPTION_SCAN_ITEMS);
    }

    public function get_last_updated_at()
    {
        $value = get_option(self::OPTION_UPDATED_AT, '');

        return is_string($value) ? $value : '';
    }
}
