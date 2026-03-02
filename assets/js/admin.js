/**
 * Axiom WP Migrate - Admin JavaScript
 */

(function($) {
    'use strict';

    const AWM = {
        /**
         * Initialize admin scripts
         */
        init: function() {
            this.bindEvents();
            this.checkConnections();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Connection modal
            $('#awm-add-connection').on('click', this.openConnectionModal);
            $('.awm-cancel-connection').on('click', this.closeConnectionModal);
            $('#awm-connection-form').on('submit', this.saveConnection);
            $('.awm-edit-connection').on('click', this.editConnection);
            $('.awm-delete-connection').on('click', this.deleteConnection);
            $('.awm-test-connection').on('click', this.testConnection);

            // Migration forms
            $('#awm-push-form, #awm-pull-form').on('submit', this.submitMigration);
            $('#awm-export-form').on('submit', this.submitExport);
            $('#awm-import-form').on('submit', this.submitImport);

            // Backup operations
            $('.awm-restore-backup').on('click', this.restoreBackup);
            $('.awm-delete-backup').on('click', this.deleteBackup);
        },

        /**
         * Open connection modal
         */
        openConnectionModal: function(e) {
            e.preventDefault();
            $('#awm-connection-modal').show();
            $('#awm-modal-title').text(awm_admin.addConnectionTitle);
            $('#awm-connection-form')[0].reset();
            $('#connection_id').val('');
        },

        /**
         * Close connection modal
         */
        closeConnectionModal: function() {
            $('#awm-connection-modal').hide();
        },

        /**
         * Edit connection
         */
        editConnection: function(e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            const connectionId = $(this).data('id');
            
            // Load connection data
            $.post(ajaxurl, {
                action: 'awm_get_connection',
                nonce: awm_admin.nonce,
                connection_id: connectionId
            }, function(response) {
                if (response.success) {
                    const conn = response.data;
                    $('#connection_id').val(conn.id);
                    $('#connection_name').val(conn.name);
                    $('#connection_url').val(conn.url);
                    $('#connection_key').val(conn.key);
                    $('#awm-modal-title').text(awm_admin.editConnectionTitle);
                    $('#awm-connection-modal').show();
                }
            });
        },

        /**
         * Save connection
         */
        saveConnection: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submit = $form.find('button[type="submit"]');
            
            $submit.prop('disabled', true).text(awm_admin.saving);

            $.post(ajaxurl, $form.serialize(), function(response) {
                $submit.prop('disabled', false).text(awm_admin.saveConnection);
                
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || awm_admin.saveError);
                }
            });
        },

        /**
         * Delete connection
         */
        deleteConnection: function(e) {
            e.preventDefault();
            
            if (!confirm(awm_admin.deleteConfirm)) {
                return;
            }

            const $btn = $(this);
            const connectionId = $btn.data('id');

            $.post(ajaxurl, {
                action: 'awm_delete_connection',
                nonce: awm_admin.nonce,
                connection_id: connectionId
            }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut();
                } else {
                    alert(response.data.message || awm_admin.deleteError);
                }
            });
        },

        /**
         * Test connection
         */
        testConnection: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const $row = $btn.closest('tr');
            const $status = $row.find('.awm-connection-status');
            const connectionId = $btn.data('id');

            $status.html('<span class="awm-spinner"></span> ' + awm_admin.testing);
            $btn.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'awm_test_connection',
                nonce: awm_admin.nonce,
                connection_id: connectionId
            }, function(response) {
                $btn.prop('disabled', false);
                
                if (response.success) {
                    $status.html('<span style="color:green">✓ ' + awm_admin.connected + '</span>');
                } else {
                    $status.html('<span style="color:red">✗ ' + (response.data.message || awm_admin.connectionFailed) + '</span>');
                }
            });
        },

        /**
         * Submit migration
         */
        submitMigration: function(e) {
            e.preventDefault();

            const $form = $(this);
            const action = $form.find('input[name="action"]').val();
            const isDryRun = $form.find('input[name="dry_run"]').is(':checked');

            if (isDryRun && !confirm(awm_admin.dryRunConfirm)) {
                return;
            }

            const $submit = $form.find('button[type="submit"]');
            $submit.prop('disabled', true);

            $.post(ajaxurl, $form.serialize(), function(response) {
                $submit.prop('disabled', false);

                if (response.success) {
                    AWM.showProgress(response.data.job_id);
                } else {
                    alert(response.data.message || awm_admin.migrationError);
                }
            });
        },

        /**
         * Submit export
         */
        submitExport: function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submit = $form.find('button[type="submit"]');

            $submit.prop('disabled', true).text(awm_admin.exporting);

            $.post(ajaxurl, $form.serialize(), function(response) {
                $submit.prop('disabled', false).text(awm_admin.exportDatabase);

                if (response.success) {
                    alert(awm_admin.exportSuccess + '\n' + response.data.file);
                } else {
                    alert(awm_admin.exportError + ': ' + (response.data.message || 'Unknown error'));
                }
            });
        },

        /**
         * Submit import
         */
        submitImport: function(e) {
            e.preventDefault();

            const $form = $(this);
            const isDryRun = $form.find('input[name="dry_run"]').is(':checked');

            if (!isDryRun && !confirm(awm_admin.importWarning)) {
                return;
            }

            const formData = new FormData($form[0]);
            formData.append('action', 'awm_import');

            const $submit = $form.find('button[type="submit"]');
            $submit.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $submit.prop('disabled', false);

                    if (response.success) {
                        if (isDryRun) {
                            alert(awm_admin.dryRunComplete);
                        } else {
                            alert(awm_admin.importSuccess);
                        }
                    } else {
                        alert(awm_admin.importError + ': ' + (response.data.message || 'Unknown error'));
                    }
                }
            });
        },

        /**
         * Show migration progress
         */
        showProgress: function(jobId) {
            $('#awm-progress-modal').show();

            const pollInterval = setInterval(function() {
                $.post(ajaxurl, {
                    action: 'awm_get_job_progress',
                    nonce: awm_admin.nonce,
                    job_id: jobId
                }, function(response) {
                    if (response.success) {
                        const progress = response.data.progress;
                        const $fill = $('#awm-progress-modal .awm-progress-fill');
                        const $text = $('#awm-progress-modal .awm-progress-text');

                        $fill.css('width', progress.percentage + '%');
                        $text.text(progress.percentage + '%');

                        if (progress.percentage >= 100) {
                            clearInterval(pollInterval);
                            setTimeout(function() {
                                $('#awm-progress-modal').hide();
                                location.reload();
                            }, 1000);
                        }
                    }
                });
            }, 2000);
        },

        /**
         * Restore backup
         */
        restoreBackup: function(e) {
            e.preventDefault();

            if (!confirm(awm_admin.restoreWarning)) {
                return;
            }

            const $btn = $(this);
            const backupName = $btn.data('name');

            $.post(ajaxurl, {
                action: 'awm_restore_backup',
                nonce: awm_admin.nonce,
                backup_name: backupName
            }, function(response) {
                if (response.success) {
                    alert(awm_admin.restoreSuccess);
                    location.reload();
                } else {
                    alert(awm_admin.restoreError + ': ' + (response.data.message || 'Unknown error'));
                }
            });
        },

        /**
         * Delete backup
         */
        deleteBackup: function(e) {
            e.preventDefault();

            if (!confirm(awm_admin.deleteBackupConfirm)) {
                return;
            }

            const $btn = $(this);
            const backupName = $btn.data('name');

            $.post(ajaxurl, {
                action: 'awm_delete_backup',
                nonce: awm_admin.nonce,
                backup_name: backupName
            }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut();
                } else {
                    alert(awm_admin.deleteBackupError + ': ' + (response.data.message || 'Unknown error'));
                }
            });
        },

        /**
         * Check connections status on page load
         */
        checkConnections: function() {
            $('.awm-connection-status').each(function() {
                const $status = $(this);
                const url = $status.data('url');

                if (url) {
                    // Could implement automatic status checking
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AWM.init();
    });

})(jQuery);
