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
        
        // Check if localized data exists
        if (typeof scienceCommunitiesData === 'undefined') {
            console.error('scienceCommunitiesData is not defined. AJAX will fail.');
            $('.sc-upload-progress').hide();
            $('.sc-upload-message')
                .addClass('error')
                .html('Configuration error. Please refresh the page.');
            return;
        }

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
                console.log('Upload success response:', response);
        
                if (response.success) {
                    $('#sc-logo').val(response.data.url);
                    $('.sc-upload-message')
                        .addClass('success')
                        .html(response.data.message);
            
                    // Show preview if URL exists
                    if (response.data.url) {
                        var previewHtml = '<div class="sc-logo-preview"><img src="' + response.data.url + '" alt="Logo preview" style="max-width: 200px; margin-top: 10px;"></div>';
                        $('.sc-upload-message').after(previewHtml);
                    }
                } else {
                    $('.sc-upload-message')
                        .addClass('error')
                        .html(response.data || 'Upload failed without error message.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Upload AJAX error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
        
                var errorMsg = 'Upload failed. ';
                if (xhr.responseText) {
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        errorMsg += errorData.data || errorData.message || xhr.statusText;
                    } catch(e) {
                        errorMsg += xhr.statusText || 'Please try again.';
                    }
                } else {
                    errorMsg += 'Please try again.';
                }
        
                $('.sc-upload-message')
                    .addClass('error')
                    .html(errorMsg);
            },
            complete: function() {
                // Always hide progress and reset bar, regardless of success/failure
                $('.sc-upload-progress').hide();
                $('.sc-progress-bar').css('width', '0%');
            }
        });
        
        // Reset file input
        $(this).val('');
    });
});