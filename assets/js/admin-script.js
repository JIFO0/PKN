jQuery(document).ready(function($) {
    function updateLogoPreview(url) {
        var $preview = $('#sc-logo-preview-image');
        if (!$preview.length) return;
        if (url) {
            $preview.attr('src', url).show();
        } else {
            $preview.hide();
        }
    }

    $('#sc-logo').on('input change', function() {
        updateLogoPreview($(this).val());
    });

    // Handle logo file upload
    $('#sc-logo-upload').on('change', function(e) {
        var file = e.target.files[0];
        if (!file) return;
        
        // Show progress
        $('.sc-upload-progress').show();
        $('.sc-upload-message').html('').removeClass('error success');
        
        var formData = new FormData();
        formData.append('action', 'sc_upload_logo');
        if (typeof scienceCommunitiesData === 'undefined') {
            $('.sc-upload-message').addClass('error').html('Upload config missing. Refresh page and try again.');
            return;
        }

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
                    
                    // Create preview container if it doesn't exist
                    if (!$('#sc-logo-preview-container').length) {
                        var previewHtml = '<div id="sc-logo-preview-container" class="sc-logo-preview"><img id="sc-logo-preview-image" src="' + response.data.url + '" alt="Logo preview" style="max-width: 200px; margin-top: 10px;"></div>';
                        $('.sc-upload-message').after(previewHtml);
                    }
                    
                    updateLogoPreview(response.data.url);
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

    $('#sc-gallery-upload').on('change', function(e) {
        var files = Array.from(e.target.files || []);
        if (!files.length || typeof scienceCommunitiesData === 'undefined') return;

        var $progress = $('.sc-gallery-progress');
        var $bar = $progress.find('.sc-progress-bar');
        var $message = $('.sc-gallery-upload-message');
        var $textarea = $('#sc-gallery-images');
        var uploadedCount = 0;
        var failedCount = 0;
        var total = files.length;

        $message.html('').removeClass('error success');
        $progress.show();
        $bar.css('width', '0%');

        function appendUrl(url) {
            var current = $textarea.val().trim();
            $textarea.val(current ? current + '\n' + url : url);
        }

        function uploadSingle(index) {
            if (index >= total) {
                $progress.hide();
                $bar.css('width', '0%');
                var cls = failedCount ? 'error' : 'success';
                $message.addClass(cls).html('Gallery upload complete. Uploaded: ' + uploadedCount + ', failed: ' + failedCount + '.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'sc_upload_logo');
            formData.append('nonce', scienceCommunitiesData.nonce);
            formData.append('logo_file', files[index]);

            $.ajax({
                url: scienceCommunitiesData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function(response) {
                if (response.success && response.data && response.data.url) {
                    appendUrl(response.data.url);
                    uploadedCount += 1;
                } else {
                    failedCount += 1;
                }
            }).fail(function() {
                failedCount += 1;
            }).always(function() {
                var percent = Math.round(((index + 1) / total) * 100);
                $bar.css('width', percent + '%');
                uploadSingle(index + 1);
            });
        }

        uploadSingle(0);
        $(this).val('');
    });
});

    $('#sc-pull-facebook').on('click', function() {
        if (typeof scienceCommunitiesData === 'undefined') return;
        var communityId = $(this).data('community-id');
        var facebook = $('#sc-facebook').val();
        var $status = $('#sc-facebook-pull-status');
        $status.text('Fetching data from Facebook...');

        $.post(scienceCommunitiesData.ajaxUrl, {
            action: 'sc_pull_facebook_data',
            nonce: scienceCommunitiesData.nonce,
            community_id: communityId,
            facebook: facebook
        }).done(function(response) {
            if (response.success) {
                var d = response.data.data || {};
                if (d.picture_url) { $('#sc-logo').val(d.picture_url); updateLogoPreview(d.picture_url); }
                if (d.about && !$('#sc-shortdescription').val()) { $('#sc-shortdescription').val(d.about); }
                if (d.description && !$('#sc-description').val()) { $('#sc-description').val(d.description); }
                $status.text('Facebook data fetched. Save the form to persist changes.');
            } else {
                $status.text(response.data || 'Could not fetch Facebook data.');
            }
        }).fail(function() { $status.text('Request failed. Please try again later.'); });
    });
