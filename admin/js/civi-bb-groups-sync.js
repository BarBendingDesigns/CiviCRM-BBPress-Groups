// Handles making a manual sync request, on the CiviCRM BBPress Groups - Manual Sync page
jQuery(document).ready(function($){
	'use strict';
	
    // When the manual sync now button is clicked...
    $('#civibbg-groups-sync-now').on('click', function(){

        $('#civibbg-groups-sync-now').prop('disabled', true);
        
        $('#civibbg-errors, #civibbg-results').empty();
        
        $('#civibbg-messages').empty()
            .append($('<h3></h3>').text('Messages'))
            .append($('<p></p>').text('Starting sync. Please wait: This can take a while if there are many members to process...'));
            
        var data = {
            action: 'civi_bb_do_sync',
            civi_bb_sync_nonce: $('#civi_bb_sync_nonce').val()
        }
        
        $.post(ajaxurl, data).done(function(result){
            $('#civibbg-messages').append($('<p></p>').text('Syncing finished.'));
            
            var pretty_result = JSON.stringify(result, null, '\t');
            $('#civibbg-results')
            .append($('<h3></h3>').text('Results'))
            .append($('<pre></pre>').text(pretty_result));
            
        }).fail(function(xhr, status, error){
            $('#civibbg-messages').append($('<p></p>').text('Syncing stopped.'));
            
            $('#civibbg-errors')
            .append($('<h3></h3>').text('Errors'))
            .append($('<p></p>').text('Error encountered. Aborted.'));
            
            if(xhr.responseJSON && !lodash.isEmpty(xhr.responseJSON.errors)){
                var error_info = JSON.stringify(xhr.responseJSON.errors, null, '\t');
                $('#civibbg-errors').append($('<pre></pre>').text(error_info));
            }
            
        }).always(function(){
            $('#civibbg-groups-sync-now').prop('disabled', false);
        });
        
    });
    
});