(function($) {
    'use strict';

    $(document).ready(function() {
        // Single course sync (from metabox)
        $('.cloudsync-manual-sync').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var courseId = $button.data('course-id');

            if (!courseId) {
                alert('ID de curso inválido');
                return;
            }

            $button.prop('disabled', true).text(cloudSyncLD.strings.syncing);

            $.ajax({
                url: cloudSyncLD.ajax_url,
                type: 'POST',
                data: {
                    action: 'ld_sync_course_to_cloud',
                    nonce: cloudSyncLD.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('✓ ' + cloudSyncLD.strings.success);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        alert(response.data.message || cloudSyncLD.strings.error);
                        $button.prop('disabled', false).text('Sincronizar Ahora');
                    }
                },
                error: function() {
                    alert(cloudSyncLD.strings.error);
                    $button.prop('disabled', false).text('Sincronizar Ahora');
                }
            });
        });

        // Bulk course sync (from management page)
        $('.cloudsync-sync-course').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var courseId = $button.data('course-id');

            $button.prop('disabled', true).text(cloudSyncLD.strings.syncing);

            $.ajax({
                url: cloudSyncLD.ajax_url,
                type: 'POST',
                data: {
                    action: 'ld_sync_course_to_cloud',
                    nonce: cloudSyncLD.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('✓ ' + cloudSyncLD.strings.success)
                               .css('color', '#46b450');

                        // Update status in the row
                        $button.closest('tr').find('td:nth-child(4)')
                               .html('<span style="color: #46b450;">● Sincronizado</span>');

                        // Update last sync
                        $button.closest('tr').find('td:nth-child(5)')
                               .text('Hace unos segundos');

                        setTimeout(function() {
                            $button.prop('disabled', false).text('Sincronizar');
                        }, 2000);
                    } else {
                        alert(response.data.message || cloudSyncLD.strings.error);
                        $button.prop('disabled', false).text('Sincronizar');
                    }
                },
                error: function() {
                    alert(cloudSyncLD.strings.error);
                    $button.prop('disabled', false).text('Sincronizar');
                }
            });
        });

        // Select all checkbox
        $('#cloudsync-select-all').on('change', function() {
            $('.cloudsync-course-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Bulk sync all button
        $('#cloudsync-bulk-sync-all').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var courseIds = [];

            $('.cloudsync-course-checkbox:checked').each(function() {
                courseIds.push($(this).val());
            });

            if (courseIds.length === 0) {
                courseIds = [];
                $('.cloudsync-course-checkbox').each(function() {
                    courseIds.push($(this).val());
                });
            }

            if (courseIds.length === 0) {
                alert('No hay cursos para sincronizar');
                return;
            }

            if (!confirm('¿Sincronizar ' + courseIds.length + ' curso(s)?')) {
                return;
            }

            $button.prop('disabled', true);
            $('.cloudsync-sync-progress').show();

            var totalCourses = courseIds.length;
            var processedCourses = 0;

            function syncNext() {
                if (courseIds.length === 0) {
                    // All done
                    $button.prop('disabled', false);
                    $('.progress-text').text('✓ Completado: ' + processedCourses + ' / ' + totalCourses);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    return;
                }

                var courseId = courseIds.shift();

                $.ajax({
                    url: cloudSyncLD.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ld_sync_course_to_cloud',
                        nonce: cloudSyncLD.nonce,
                        course_id: courseId
                    },
                    success: function(response) {
                        processedCourses++;
                        var percentage = (processedCourses / totalCourses) * 100;

                        $('.progress-fill').css('width', percentage + '%');
                        $('.progress-text').text(processedCourses + ' / ' + totalCourses);

                        // Continue with next
                        syncNext();
                    },
                    error: function() {
                        processedCourses++;
                        // Continue even if one fails
                        syncNext();
                    }
                });
            }

            // Start syncing
            syncNext();
        });
    });

})(jQuery);
