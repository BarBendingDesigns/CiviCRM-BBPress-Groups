// Handles validation of the My Account form from the [civi-bbg-user-account] shortcode
// and display of form submission results
jQuery(document).ready(function($){
	'use strict';
	
	var civibbg_account_validate_email = function(){
	    var f = $('#civibbg-account-form input[name="civibbg[user_email]"]')[0];
	    if(!f.checkValidity()){
	        if($(f).parent().find('.text-danger').length === 0){
	            $(f).after($('<p></p>').addClass('text-danger civibbg-error').text('Please enter a valid email address.'));
	        }
	    } else {
	        $(f).parent().find('.text-danger').remove();
	    }
	}
	
	var civibbg_account_validate_display_name = function(){
	    var f = $('#civibbg-account-form input[name="civibbg[display_name]"]')[0];
	    if(!f.checkValidity()){
	        if($(f).parent().find('.text-danger').length === 0){
	            $(f).after($('<p></p>').addClass('text-danger civibbg-error').text('Please enter a display name.'));
	        }
	    } else {
	        $(f).parent().find('.text-danger').remove();
	    }
	}
	
	var civibbg_account_validate_passwords = function(){
	    var op = $('#civibbg-account-form input[name="civibbg[old_password]"]')[0];
	    var np = $('#civibbg-account-form input[name="civibbg[new_password]"]')[0];
	    var cp = $('#civibbg-account-form input[name="civibbg[confirm_password]"]')[0];
	    
	    var rePass = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/;
	    
	    if($(np).val() !== '' || $(cp).val() !== ''){
	        if($(np).val() !== $(cp).val()){
	            np.setCustomValidity('Passwords do not match');
	            $(np).next().removeClass('civibbg_valid').addClass('text-danger civibbg-error').text('Passwords do not match.');
	            
	            cp.setCustomValidity('Passwords do not match');
                $(cp).next().removeClass('civibbg_valid').addClass('text-danger civibbg-error').text('Passwords do not match.');
                    
	        } else if(!$(np).val().match(rePass)){
                np.setCustomValidity('Password must be at least 8 characters long, an contain at least one number, upper case letter and lowercase letter.');
                $(np).next().removeClass('civibbg_valid').addClass('text-danger civibbg-error')
                    .text('Password must be at least 8 characters long, an contain at least one number, upper case letter and lowercase letter.');
                    
	            cp.setCustomValidity('');
	            $(cp).next().removeClass('text-danger civibbg-error').addClass('civibbg_valid').text('');
	                
	        } else {
	            np.setCustomValidity('');
	            $(np).next().removeClass('text-danger civibbg-error').addClass('civibbg_valid').text('');
	            
	            cp.setCustomValidity('');
	            $(cp).next().removeClass('text-danger civibbg-error').addClass('civibbg_valid').text('');
	        }
	        
	        if($(op).val() === ''){
	            op.setCustomValidity('Old password is required if you want to change your password.');
	            $(op).next().removeClass('civibbg_valid').addClass('text-danger civibbg-error')
                    .text('Old password is required if you want to change your password.');
                    
	        } else {
	            op.setCustomValidity('');
	            $(op).next().removeClass('text-danger civibbg-error').addClass('civibbg_valid').text('');
	        }
	        
	    } else {
	        op.setCustomValidity('');
	        np.setCustomValidity('');
	        cp.setCustomValidity('');
	        $('#civibbg-account-form input[type="password"]').next().removeClass('text-danger civibbg-error').addClass('civibbg_valid').text('');
	    }
	}
	
	$('#civibbg-account-form input[name="civibbg[user_email]"]').on('input change', function(event){
	    civibbg_account_validate_email();
	});
	
	$('#civibbg-account-form input[name="civibbg[display_name]"]').on('input change', function(event){
	    civibbg_account_validate_display_name();
	});
	
	$('#civibbg-account-form input[type="password"]').on('input change', function(event){
	    civibbg_account_validate_passwords();
	});
	
	// Make sure all fields are valid before submitting form
	$('#civibbg-account-submit').on('click', function(event){
	    event.preventDefault();
	    
	    civibbg_account_validate_email();
	    civibbg_account_validate_display_name();
	    civibbg_account_validate_passwords();
	    
	    if($('#civibbg-account-form')[0].checkValidity()){
	        $('#civibbg-account-form')[0].submit();
	    
	    } 
	    
	});
	
	// If the form has been submitted, output the result
	if(civibbg_account_result.submitted > 0){
	    var classes = civibbg_account_result.success > 0 ? 'alert alert-success' : 'alert alert-danger';
	    var message = civibbg_account_result.success > 0 ? civibbg_account_result.message : 'Failed to update account details: ' + civibbg_account_result.message;
	    var close_button = $('<button type="button" class="close" aria-label="Close"><span aria-hidden="true">&times;</span></button>');
	    $('#civibbg-account-result').append($('<div></div>').addClass(classes).text(message).append(close_button));
	}
	
	// Let the user dismiss the result message. (Not using Bootstrap default dismiss functionality in case theme is not using Bootstrap)
	$('#civibbg-account-result').on('click', 'div.alert-success button, div.alert-danger button', function(event){
	   $(event.target).closest('div').remove(); 
	});
	
});
