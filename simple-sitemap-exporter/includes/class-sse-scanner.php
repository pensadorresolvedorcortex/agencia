<?php

if (! defined('ABSPATH')) {
    exit;
}

class SSE_Scanner
{
    public function create_initial_state()
    {
        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        $post_types = array_values(array_filter($post_types, function ($post_type) {
            return 'attachment' !== $post_type;
        }));

        $taxonomies = array_values(get_taxonomies(array('public' => true), 'names'));

        return array(
            'phase'           => 'special',
            'post_types'      => $post_types,
            'post_type_index' => 0,
            'post_paged'      => 1,
            'taxonomies'      => $taxonomies,
            'tax_index'       => 0,
            'term_offset'     => 0,
        );
    }

    public function run_batch($state, $items, $batch_size = 300)
    {
        if (! is_array($state) || empty($state)) {
            $state = $this->create_initial_state();
        }

        if (! is_array($items)) {
            $items = array();
        }

        $batch_size = max(50, absint($batch_size));

        if ('special' === $state['phase']) {
            $this->scan_special_pages($items);
            $state['phase'] = 'posts';

            return array(
                'state'     => $state,
                'items'     => $items,
                'done'      => false,
                'processed' => 1,
            );
        }

        if ('posts' === $state['phase']) {
            $processed = $this->scan_posts_step($state, $items, $batch_size);

            return array(
                'state'     => $state,
                'items'     => $items,
                'done'      => 'done' === $state['phase'],
                'processed' => $processed,
            );
        }

        if ('terms' === $state['phase']) {
            $processed = $this->scan_terms_step($state, $items, $batch_size);

            return array(
                'state'     => $state,
                'items'     => $items,
                'done'      => 'done' === $state['phase'],
                'processed' => $processed,
            );
        }

        return array(
            'state'     => $state,
            'items'     => $items,
            'done'      => true,
            'processed' => 0,
        );
    }

    public function finalize_items($items)
    {
        uasort($items, function ($a, $b) {
            return strcmp($a['loc'], $b['loc']);
        });

        return array_values($items);
    }

    public function scan_urls()
    {
        $state = $this->create_initial_state();
        $items = array();

        while ('done' !== $state['phase']) {
            $result = $this->run_batch($state, $items, 1000);
            $state  = $result['state'];
            $items  = $result['items'];
        }

        return $this->finalize_items($items);
    }

    private function scan_posts_step(&$state, &$items, $batch_size)
    {
        $post_types = isset($state['post_types']) && is_array($state['post_types']) ? $state['post_types'] : array();
        $index      = isset($state['post_type_index']) ? (int) $state['post_type_index'] : 0;
        $paged      = isset($state['post_paged']) ? (int) $state['post_paged'] : 1;

        if (! isset($post_types[$index])) {
            $state['phase'] = 'terms';
            return 0;
        }

        $post_type = $post_types[$index];

        $query = new WP_Query(
            array(
                'post_type'              => $post_type,
                'post_status'            => 'publish',
                'posts_per_page'         => $batch_size,
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

        $processed = 0;

        if (! empty($query->posts)) {
            foreach ($query->posts as $post_id) {
                $processed++;

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
        }

        $max_pages = (int) $query->max_num_pages;

        if ($max_pages > 0 && $paged < $max_pages) {
            $state['post_paged'] = $paged + 1;
        } else {
            $state['post_type_index'] = $index + 1;
            $state['post_paged'] = 1;

            if (! isset($post_types[$state['post_type_index']])) {
                $state['phase'] = 'terms';
            }
        }

        wp_reset_postdata();

        return $processed;
    }

    private function scan_terms_step(&$state, &$items, $batch_size)
    {
        $taxonomies = isset($state['taxonomies']) && is_array($state['taxonomies']) ? $state['taxonomies'] : array();
        $tax_index  = isset($state['tax_index']) ? (int) $state['tax_index'] : 0;
        $offset     = isset($state['term_offset']) ? (int) $state['term_offset'] : 0;

        if (! isset($taxonomies[$tax_index])) {
            $state['phase'] = 'done';
            return 0;
        }

        $taxonomy = $taxonomies[$tax_index];

        $terms = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => $batch_size,
                'offset'     => $offset,
            )
        );

        if (is_wp_error($terms)) {
            $state['tax_index'] = $tax_index + 1;
            $state['term_offset'] = 0;

            if (! isset($taxonomies[$state['tax_index']])) {
                $state['phase'] = 'done';
            }

            return 0;
        }

        $processed = count($terms);

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

        if ($processed >= $batch_size) {
            $state['term_offset'] = $offset + $processed;
        } else {
            $state['tax_index'] = $tax_index + 1;
            $state['term_offset'] = 0;

            if (! isset($taxonomies[$state['tax_index']])) {
                $state['phase'] = 'done';
            }
        }

        return $processed;
    }

    private function scan_special_pages(&$items)
    {
        $home = $this->normalize_url(home_url('/'));
        if ($home) {
            $items[$home] = array(
                'loc'     => $home,
                'lastmod' => gmdate('c'),
            );
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
