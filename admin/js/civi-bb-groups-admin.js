// Handles adding/editing/deleting CiviCRM groups to WordPress roles mapping,
// on CiviCRM BBPress Settings page
jQuery(document).ready(function($){
	'use strict';
	
	// localized script: civibbg_data: {civi_groups, wp_roles, bb_roles, bb_on}
	var bb_on = lodash.toInteger(civibbg_data.bb_on);
	
	// Construct and return a WP roles or BB roles dropdown selector
	var getRolesDropdown = function(current, type){
	    var roles, c;
	    if(type === 'bb'){
	        roles = civibbg_data.bb_roles; 
	        c = 'civibbg_bb_role';
	    } else {
	        roles =  civibbg_data.wp_roles;
	        c = 'civibbg_role';
	    }

	    var dropdown = $('<select></select>').addClass(c).html('<option value="">Select role...</option>');
	    var options = lodash.map(roles, function(r, k){
	        return $('<option></option>').text(r).val(k);
	    });
	    var current_detail = lodash.has(roles, current) ? roles[current] : '(unknown role)';
	    dropdown.append(options).val(current).data('undo', current).data('undo_name', current_detail);
	    return dropdown;
	}
    
    // Construct and return a CiviCRM groups dropdown selector
    var getGroupsDropdown = function(current){
        var dropdown = $('<select></select>').addClass('civibbg_group').html('<option value="">Select group...</option>');
        var options = lodash.chain(civibbg_data.civi_groups).sortBy('title').map(function(g, k){
	        return $('<option></option>').text(g.title).val(g.id);
	    }).value();
	    var current_detail = lodash.find(civibbg_data.civi_groups, {id: lodash.toInteger(current)});
	    if(lodash.isUndefined(current_detail)){
	        current_detail = {id: current, title: '(unknown group)'};
	    }
	    dropdown.append(options).val(current).data('undo', current).data('undo_name', current_detail.title);
	    return dropdown;
    }

    // Create a label element
    var getLabel = function(text){
        return $('<label></label>').addClass('civibbg_hideSm').text(text);
    }
    
    // Create a checkbox element
    var getCheckbox = function(current){
        current = lodash.toInteger(current);
        return $('<input type="checkbox"/>').attr("checked", current ? true : false).addClass('civibbg_removals').data('undo', current);
    }

    var new_row = 0;

    // Add a new row in edit mode
    $('.civibbg-add-group').on('click', function(){
        $('#civicrm-bbpress-groups-page .submit input[type=submit]').prop( "disabled", true );
        
        var ok = $("<button type='button'></button>").addClass('civibbg-groups-ok button button-primary').text('Ok');
        var canx = $("<button type='button'></button>").addClass('civibbg-groups-delete button button-default').text('Delete');
        var row = $('<tr></tr>').attr('id', 'civibbg-new-'+new_row)
            .append($('<td></td>').append(getLabel('CiviCRM Group')).append(getGroupsDropdown('')))
            .append($('<td></td>').append(getLabel('WP Role')).append(getRolesDropdown('', 'wp')))
            .append($('<td></td>').append(getLabel('BBPress Role')).append(getRolesDropdown('', 'bb')))
            .append($('<td></td>').append(getLabel('Sync Removals?')).append(getCheckbox(false)))
            .append($('<td></td>').append(ok).append(canx));
        $('.civibbg-groups-map tbody').append(row);
        new_row++;
        $('.civibbg-groups-map tbody tr')[new_row].scrollIntoView();
    });
    
    // Delete this row
    $('.civibbg-groups-map').on('click', '.civibbg-groups-delete', function(){
        $(this).parent().parent().remove();
        $('#civicrm-bbpress-groups-page .submit input[type=submit]').prop( "disabled", false );
    })
    
    // Change the row back to view mode, with the changes
    $('.civibbg-groups-map').on('click', '.civibbg-groups-ok', function(){
        var group = $(this).parent().parent().find('.civibbg_group');
        var role = $(this).parent().parent().find('.civibbg_role');
        var bb_role = $(this).parent().parent().find('.civibbg_bb_role');
        var sync_removals = $(this).parent().parent().find('.civibbg_removals');
        var id = $(this).parent().parent().attr('id');
        
        if(lodash.isEmpty(group[0].value)){
            $(group).after($('<p></p>').addClass('civibbg_error').text('Please select a group.'));
            return;
        }
        
        if(lodash.isEmpty(role[0].value)){
            $(role).after($('<p></p>').addClass('civibbg_error').text('Please select a role.'));
            return;
        }
        
        if(lodash.isEmpty(bb_role[0].value) && bb_on){
            $(bb_role).after($('<p></p>').addClass('civibbg_error').text('Please select a role.'));
            return;
        }
        
        var edit = $("<button type='button'></button>").addClass('civibbg-groups-edit button button-default').text('Edit');
        var del = $("<button type='button'></button>").addClass('civibbg-groups-delete button button-default').text('Delete');
        
        $(this).parent().parent().empty()
            .append($('<td></td>').html('<p>'+group[0].selectedOptions[0].text+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][group]" class="civibbg_group_field" value="'+group[0].value+'"/>').prepend(getLabel('CiviCRM Group')))
            .append($('<td></td>').html('<p>'+role[0].selectedOptions[0].text+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][role]" class="civibbg_role_field" value="'+role[0].value+'"/>').prepend(getLabel('WP Role')))
            .append($('<td></td>').html('<p>'+bb_role[0].selectedOptions[0].text+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][bb_role]" class="civibbg_bb_role_field" value="'+bb_role[0].value+'"/>').prepend(getLabel('BBPress Role')))
            .append($('<td></td>').html('<p>'+(sync_removals[0].checked ? 'Yes' : 'No')+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][sync_removals]" class="civibbg_removals_field" value="'+(lodash.toInteger(sync_removals[0].checked))+'"/>').prepend(getLabel('Sync Removals?')))
            .append($('<td></td>').append(edit).append(del));
            
        $('#civicrm-bbpress-groups-page .submit input[type=submit]').prop( "disabled", false );
    })
    
    // Change the row back to view mode, without any changes
    $('.civibbg-groups-map').on('click', '.civibbg-groups-cancel', function(){
        var group = $(this).parent().parent().find('.civibbg_group');
        var group_id = $(group[0]).data('undo');
        var group_name = $(group[0]).data('undo_name');
        
        var role = $(this).parent().parent().find('.civibbg_role');
        var role_id = $(role[0]).data('undo');
        var role_name = $(role[0]).data('undo_name');
        
        var bb_role = $(this).parent().parent().find('.civibbg_bb_role');
        var bb_role_id = $(bb_role[0]).data('undo');
        var bb_role_name = $(bb_role[0]).data('undo_name');
        
        var sync_removals = $(this).parent().parent().find('.civibbg_removals');
        var removals = $(sync_removals[0]).data('undo');
        var removals_name = removals ? 'Yes' : 'No';
        
        var id = $(this).parent().parent().attr('id');
        
        var edit = $("<button type='button'></button>").addClass('civibbg-groups-edit button button-default').text('Edit');
        var del = $("<button type='button'></button>").addClass('civibbg-groups-delete button button-default').text('Delete');
        
        $(this).parent().parent().empty()
            .append($('<td></td>').html('<p>'+group_name+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][group]" class="civibbg_group_field" value="'+group_id+'"/>').prepend(getLabel('CiviCRM Group')))
            .append($('<td></td>').html('<p>'+role_name+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][role]" class="civibbg_role_field" value="'+role_id+'"/>').prepend(getLabel('WP Role')))
            .append($('<td></td>').html('<p>'+bb_role_name+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][bb_role]" class="civibbg_bb_role_field" value="'+bb_role_id+'"/>').prepend(getLabel('BBPress Role')))
            .append($('<td></td>').html('<p>'+removals_name+'</p><input type="hidden" name="civi-bb-groups-syncing-map['+id+'][sync_removals]" class="civibbg_removals_field" value="'+removals+'"/>').prepend(getLabel('Sync Removals?')))
            .append($('<td></td>').append(edit).append(del));
            
        $('#civicrm-bbpress-groups-page .submit input[type=submit]').prop( "disabled", false );
    })
    
    // Change the row from view mode to edit mode
    $('.civibbg-groups-map').on('click', '.civibbg-groups-edit', function(){
        $('#civicrm-bbpress-groups-page .submit input[type=submit]').prop( "disabled", true );
        
        var group = $(this).parent().parent().find('.civibbg_group_field');
        var role = $(this).parent().parent().find('.civibbg_role_field');
        var bb_role = $(this).parent().parent().find('.civibbg_bb_role_field');
        var sync_removals = $(this).parent().parent().find('.civibbg_removals_field');

        var ok = $("<button type='button'></button>").addClass('civibbg-groups-ok button button-primary').text('Ok');
        var canx = $("<button type='button'></button>").addClass('civibbg-groups-cancel button button-default').text('Cancel');
        $(this).parent().parent().empty()
            .append($('<td></td>').append(getLabel('CiviCRM Group')).append(getGroupsDropdown(group[0].value)))
            .append($('<td></td>').append(getLabel('WP Role')).append(getRolesDropdown(role[0].value, 'wp')))
            .append($('<td></td>').append(getLabel('BBPress Role')).append(getRolesDropdown(bb_role[0].value, 'bb')))
            .append($('<td></td>').append(getLabel('Sync Removals?')).append(getCheckbox(sync_removals[0].value)))
            .append($('<td></td>').append(ok).append(canx));
    })
    
    // Remove any error messages for the selector, when group or role selector changes
    $('.civibbg-groups-map').on('change', '.civibbg_group, .civibbg_role, .civibbg_bb_role', function(){
        var val = $(this).val();
        if(!lodash.isEmpty(val)){
            $(this).next().remove();
        }
    });


});
