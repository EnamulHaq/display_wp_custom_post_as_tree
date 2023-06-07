<?php

$all_posts_arr = [];


function display_posts_as_treef($post_id) {
    $post = get_post($post_id);
    $output = array();

    if ($post) {
        $ancestors = get_post_ancestors($post);
        $ancestors = array_reverse($ancestors);

        $tree = &$output;

        foreach ($ancestors as $ancestor) {
            $ancestor_post = get_post($ancestor);
            $tree[] = array(
                'title' => get_the_title($ancestor_post->ID),
                'permalink' => get_permalink($ancestor_post->ID),
                'post_id' => $ancestor_post->ID,
                'children' => array()
            );
            $tree = &$tree[count($tree) - 1]['children'];
        }

        $tree[] = array(
            'title' => get_the_title($post->ID),
            'permalink' => get_permalink($post->ID),
            'post_id' => $post->ID,
            'children' => array()
        );
        $tree = &$tree[count($tree) - 1]['children'];

        // Get child posts of the current post ID
        $child_posts = get_posts(array(
            'post_type' => 'lessons', // Replace 'post' with your custom post type if applicable
            'post_status' => 'publish',
            'post_parent' => $post->ID,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'posts_per_page' => -1
        ));

        // Check if there are child posts
        if ($child_posts) {
            foreach ($child_posts as $child_post) {
                $tree[] = array(
                    'title' => get_the_title($child_post->ID),
                    'permalink' => get_permalink($child_post->ID),
                    'post_id' => $child_post->ID,
                    'children' => array()
                );
            }
        }
    }

    return $output;
}

$post_id = get_queried_object_id();

$all_posts = display_posts_as_treef($post_id);

function getAllPostIds($array, &$postIds) {
    foreach ($array as $item) {
        $postId = $item['post_id'];
        $postIds[] = $postId;

        if (isset($item['children']) && !empty($item['children'])) {
            getAllPostIds($item['children'], $postIds);
        }
    }
}

$postIds = [];
getAllPostIds($all_posts, $postIds);

function getFirstLevelChildren($parentID) {
    $args = array(
        'post_type' => 'lessons',
        'post_parent' => $parentID,
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    );

    return get_posts($args);
}

foreach ($postIds as $postSingleId) {
    $firstLevelChildren = getFirstLevelChildren($postSingleId);
    foreach ($firstLevelChildren as $singleFirstLevel) {

        if(!in_array($singleFirstLevel->ID, $postIds)) {
            $new_array = array(
                'title' => get_the_title($singleFirstLevel->ID),
                'permalink' => get_permalink($singleFirstLevel->ID),
                'post_id' => $singleFirstLevel->ID,
                'children' => array()
            );
            insertArrayByPostID($all_posts, $postSingleId, $new_array);
        }
    }
}

function insertArrayByPostID(&$array, $postID, $newArray) {
    foreach ($array as $key => &$item) {
        if ($item['post_id'] === $postID) {
            $item['children'][] = $newArray;
            return true;
        }
        if (!empty($item['children'])) {
            if (insertArrayByPostID($item['children'], $postID, $newArray)) {
                return true;
            }
        }
    }
    return false;
}

function get_top_parent_posts($grandparent_post_id) {
    $top_parent_posts = array();
    $post_id = get_queried_object_id();
    $get_books_meta_value = get_post_meta($post_id, 'related_book', true);

    $args = array(
        'post_type' => 'lessons', // Replace 'post' with your custom post type if applicable
        'post_status' => 'publish',
        'post_parent' => 0, // Retrieve posts with no parent
        'orderby' => 'menu_order',
        'meta_value'       => $get_books_meta_value,
        'meta_key'         => 'related_book',
        'order' => 'ASC',
        'posts_per_page' => -1,
        'post__not_in' => array($grandparent_post_id)
    );

    $top_parent_query = new WP_Query($args);

    if ($top_parent_query->have_posts()) {
        while ($top_parent_query->have_posts()) {
            $top_parent_query->the_post();
            $top_parent_posts[] = get_post();
        }
        wp_reset_postdata();
    }

    return $top_parent_posts;
}

function get_first_parent_id_recursive($post_id) {
    $parent_id = get_post_field('post_parent', $post_id);

    if ($parent_id) {
        return get_first_parent_id_recursive($parent_id);
    }

    return $post_id; // Return the current post ID when there are no more parents
}

$grandparent_post_id = get_first_parent_id_recursive($post_id);


$top_parent_post = [];


$top_parent_posts = get_top_parent_posts($grandparent_post_id);

if(!empty($top_parent_posts)) {
    foreach ($top_parent_posts as $top_parent_post_data) {
        $top_parent_post[] = array(
            'title' => get_the_title($top_parent_post_data->ID),
            'permalink' => get_permalink($top_parent_post_data->ID),
            'post_id' => $top_parent_post_data->ID,
            'children' => array()
        );
    }
}

function sort_by_title($a, $b) {
    return strcmp($a['title'], $b['title']);
}

$data = array_merge($all_posts, $top_parent_post);

usort($data, 'sort_by_title');

function generateTree($array) {
    $post_id = get_queried_object_id();

    echo "<ul>";
    foreach ($array as $item) {
        $active_class = $item["title"] === get_the_title($post_id) ? 'active' : '';
        echo "<li>";
        echo "<a class='".$active_class."' href='{$item['permalink']}' >{$item['title']}</a>";

        if (!empty($item['children'])) {
            generateTree($item['children']);
        }

        echo "</li>";
    }
    echo "</ul>";
}

?>