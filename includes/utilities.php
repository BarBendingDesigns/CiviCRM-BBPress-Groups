<?php
/**
 * General utility functions
 */

// Sanitize a thing, depending on type
function civibbg_sanitize_thing($thing, $type, $for_db = false){
    switch($type){
        case 'text':
        case 'string':
            return sanitize_text_field($thing);
            break;
        case 'textarea':
            return sanitize_textarea_field($thing);
            break;
        case 'int':
        case 'integer':
        case 'image':
        case 'dropdown': // The $thing should be the ID of the selected item in the dropdown
            return intval($thing);
            break;
        case 'bool':
        case 'boolean':
        case 'checkbox':
            return ( ( isset( $thing ) && true == $thing ) ? true : false );
            break;
        case 'year':
            $thing = intval($thing);
            return ( ($thing >= 1990 && $thing <= 2100) ? $thing : 0);
            break;
        case 'url':
            return $for_db ? esc_url($thing, null, 'db') : esc_url($thing);
            break;
        case 'email':
            return sanitize_email($thing);
            break;
        case 'float':
            $thing = (string)$thing;
            $thing = preg_replace('[^\d\.]', '', $thing);
            $thing = (float)$thing;
            return $thing;
            break;
        case 'array':
            if(!is_array($thing)){ return []; }
            foreach($thing as $k=>$v){
                $thing[$k] = sanitize_text_field($v);
            }
            return $thing;
            break;
        case 'groups-map':
            if(!is_array($thing) || empty($thing)){ return []; }
            $sanitized = [];
            $duplicate_check = [];
            foreach($thing as $v){
                foreach($v as $i=>$j){
                    $v[$i] = sanitize_text_field($j);
                }
                if(!in_array($v['group'], $duplicate_check)){
                    $sanitized[] = $v;
                    $duplicate_check[] = $v['group'];
                }
            }
            return $sanitized;
            break;
        case 'role-hierarchy':
            if(!is_array($thing) || empty($thing)){ return []; }
            $sanitized = [];
            foreach($thing as $role=>$order){
                $role = sanitize_key($role);
                $sanitized[$role] = absint($order);
            }
            return $sanitized;
            break;
        case 'shortcode':
            return wp_kses($thing, '');
            break;
        default:
            return sanitize_text_field($thing);
    }
}

// Sanitize a checkbox value
function civibbg_sanitize_checkbox($val){
    return civibbg_sanitize_thing($val, 'checkbox');
}

// Sanitize a groups map value
function civibbg_sanitize_groups_map($val){
    return civibbg_sanitize_thing($val, 'groups-map');
}

// Sanitize a role hierarchy value
function civibbg_sanitize_role_hierarchy($val){
    return civibbg_sanitize_thing($val, 'role-hierarchy');
}

// Sanitize shortcode text
function civibbg_sanitize_shortcode($val){
    return civibbg_sanitize_thing($val, 'shortcode');
}

// Fetch all editable WP roles
function civibbg_get_editable_roles(){
    $all_roles = wp_roles()->roles;
    $roles = apply_filters( 'editable_roles', $all_roles );
    
    foreach($roles as $k=>$r){
        $roles[$k] = $r['name'];
    }
    if(isset($roles['administrator'])) unset($roles['administrator']);
    asort($roles);
    return $roles;
}

// Fetch all BBPress roles
function civibbg_get_bb_roles(){
    if(!civibbg_bbpress_is_on()){
        return [];
    }
    $roles = bbp_get_dynamic_roles();
    foreach($roles as $k=>$r){
        $roles[$k] = $r['name'];
    }
    asort($roles);
    return $roles;
}

// Check whether CiviCRM is on and CiviCRM API4 is available
function civibbg_civi_api_is_on(){
    return function_exists('civi_wp') && function_exists('civicrm_api4');
}

// Check whether CiviCRM is on and CiviCRM API3 is available
function civibbg_civi_api3_is_on(){
    return function_exists('civi_wp') && function_exists('civicrm_api3');
}

// Check whether BBPress is on
function civibbg_bbpress_is_on(){
    return class_exists('bbPress');
}
