<?php

if (! defined('ABSPATH')) {
    exit;
}

class SSE_Scanner
{
    public function scan_urls()
    {
        $items = array();

        $this->scan_special_pages($items);
        $this->scan_posts($items);
        $this->scan_terms($items);

        uasort($items, function ($a, $b) {
            return strcmp($a['loc'], $b['loc']);
        });

        return array_values($items);
    }

    private function scan_special_pages(&$items)
    {
        $home = $this->normalize_url(home_url('/'));
        if ($home) {
            $items[$home] = array('loc' => $home);
        }

        $posts_page_id = (int) get_option('page_for_posts');
        if ($posts_page_id > 0 && $this->is_post_indexable($posts_page_id)) {
            $url = wp_get_canonical_url($posts_page_id);
            if (empty($url)) {
                $url = get_permalink($posts_page_id);
            }

            $normalized = $this->normalize_url($url);
            if ($normalized) {
                $items[$normalized] = array(
                    'loc'     => $normalized,
                    'lastmod' => $this->get_post_lastmod($posts_page_id),
                );
            }
        }
    }

    private function scan_posts(&$items)
    {
        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        if (empty($post_types)) {
            return;
        }

        $post_types = array_values(array_filter($post_types, function ($post_type) {
            return 'attachment' !== $post_type;
        }));

        foreach ($post_types as $post_type) {
            $paged = 1;

            do {
                $query = new WP_Query(
                    array(
                        'post_type'              => $post_type,
                        'post_status'            => 'publish',
                        'posts_per_page'         => 500,
                        'paged'                  => $paged,
                        'fields'                 => 'ids',
                        'orderby'                => 'ID',
                        'order'                  => 'ASC',
                        'no_found_rows'          => false,
                        'update_post_meta_cache' => false,
                        'update_post_term_cache' => false,
                        'cache_results'          => false,
                        'ignore_sticky_posts'    => true,
                        'suppress_filters'       => true,
                    )
                );

                if (empty($query->posts)) {
                    break;
                }

                foreach ($query->posts as $post_id) {
                    if (! $this->is_post_indexable($post_id)) {
                        continue;
                    }

                    $url = wp_get_canonical_url($post_id);
                    if (empty($url)) {
                        $url = get_permalink($post_id);
                    }

                    $normalized = $this->normalize_url($url);
                    if (! $normalized) {
                        continue;
                    }

                    $items[$normalized] = array(
                        'loc'     => $normalized,
                        'lastmod' => $this->get_post_lastmod($post_id),
                    );
                }

                $paged++;
            } while ($paged <= (int) $query->max_num_pages);

            wp_reset_postdata();
        }
    }

    private function scan_terms(&$items)
    {
        $taxonomies = get_taxonomies(
            array(
                'public' => true,
            ),
            'names'
        );

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                )
            );

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (! $this->is_term_indexable($term)) {
                    continue;
                }

                $url = get_term_link($term);
                if (is_wp_error($url)) {
                    continue;
                }

                $normalized = $this->normalize_url($url);
                if (! $normalized) {
                    continue;
                }

                if (! isset($items[$normalized])) {
                    $items[$normalized] = array('loc' => $normalized);
                }
            }
        }
    }

    private function normalize_url($url)
    {
        if (! is_string($url) || '' === trim($url)) {
            return '';
        }

        $url = trim($url);
        $url = strtok($url, '?');
        $url = strtok($url, '#');

        if (! wp_http_validate_url($url)) {
            return '';
        }

        $home = wp_parse_url(home_url('/'));
        $part = wp_parse_url($url);

        if (! is_array($home) || ! is_array($part)) {
            return '';
        }

        $home_host = isset($home['host']) ? strtolower($home['host']) : '';
        $url_host  = isset($part['host']) ? strtolower($part['host']) : '';

        if ($home_host !== $url_host) {
            return '';
        }

        $path = isset($part['path']) ? $part['path'] : '/';

        return home_url(user_trailingslashit($path));
    }

    private function is_post_indexable($post_id)
    {
        if (! is_int($post_id) && ! ctype_digit((string) $post_id)) {
            return false;
        }

        $post_id = (int) $post_id;

        if ('publish' !== get_post_status($post_id)) {
            return false;
        }

        if ('private' === get_post_status($post_id) || 'trash' === get_post_status($post_id) || 'draft' === get_post_status($post_id)) {
            return false;
        }

        if ('noindex' === get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true)) {
            return false;
        }

        $rank_math_robots = get_post_meta($post_id, 'rank_math_robots', true);
        if ($this->meta_has_noindex($rank_math_robots)) {
            return false;
        }

        return true;
    }

    private function is_term_indexable($term)
    {
        if (! isset($term->term_id, $term->taxonomy)) {
            return false;
        }

        $term_id  = (int) $term->term_id;
        $taxonomy = (string) $term->taxonomy;

        if ('noindex' === get_term_meta($term_id, 'wpseo_noindex', true)) {
            return false;
        }

        $rank_math_robots = get_term_meta($term_id, 'rank_math_robots', true);
        if ($this->meta_has_noindex($rank_math_robots)) {
            return false;
        }

        $tax = get_taxonomy($taxonomy);
        if (! $tax || empty($tax->public)) {
            return false;
        }

        return true;
    }

    private function meta_has_noindex($value)
    {
        if (is_array($value)) {
            return in_array('noindex', array_map('strval', $value), true);
        }

        if (is_string($value) && '' !== $value) {
            $maybe_array = maybe_unserialize($value);
            if (is_array($maybe_array)) {
                return in_array('noindex', array_map('strval', $maybe_array), true);
            }

            return false !== stripos($value, 'noindex');
        }

        return false;
    }

    private function get_post_lastmod($post_id)
    {
        $modified_gmt = get_post_field('post_modified_gmt', $post_id);

        if (! is_string($modified_gmt) || '0000-00-00 00:00:00' === $modified_gmt || '' === $modified_gmt) {
            return '';
        }

        $timestamp = strtotime($modified_gmt . ' UTC');
        if (! $timestamp) {
            return '';
        }

        return gmdate('c', $timestamp);
    }
}
