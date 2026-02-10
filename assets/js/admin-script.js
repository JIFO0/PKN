jQuery(document).ready(function($) {
    // Handle logo file upload
    $('#sc-logo-upload').on('change', function(e) {
        var file = e.target.files[0];
        if (!file) return;
        
        // Show progress
        $('.sc-upload-progress').show();
        $('.sc-upload-message').html('').removeClass('error success');
        
        var formData = new FormData();
        formData.append('action', 'sc_upload_logo');
        formData.append('nonce', scienceCommunitiesData.nonce);
        formData.append('logo_file', file);
        
        $.ajax({
            url: scienceCommunitiesData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var percentComplete = (e.loaded / e.total) * 100;
                        $('.sc-progress-bar').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('.sc-upload-progress').hide();
                $('.sc-progress-bar').css('width', '0%');
                
                if (response.success) {
                    $('#sc-logo').val(response.data.url);
                    $('.sc-upload-message')
                        .addClass('success')
                        .html(response.data.message);
                    
                    // Update upload count
                    location.reload();
                } else {
                    $('.sc-upload-message')
                        .addClass('error')
                        .html(response.data);
                }
            },
            error: function() {
                $('.sc-upload-progress').hide();
                $('.sc-upload-message')
                    .addClass('error')
                    .html('Upload failed. Please try again.');
            }
        });
        
        // Reset file input
        $(this).val('');
    });
});