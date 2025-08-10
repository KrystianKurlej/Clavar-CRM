$(document).ready(function () {
    $('form.form').attr("novalidate", true).on("submit", function (event) {
        const method = $(this).attr("method");
        const form = $(this);
        const url = $(this).attr("action");
        const successModal = $(this).data("success-modal") || false;
        const errorModal = $(this).data("error-modal") || false;
        const refresh = $(this).data("refresh") || false;

        sendForm(method, form, url, event, refresh, successModal, errorModal);
    });

    $('.start-project').on('click', function() {
        const projectId = $(this).data('project-id');
        const csrf = $(this).data('csrf');
        startProject(projectId, csrf);
    });

    $('.stop-project').on('click', function() {
        const projectId = $(this).data('project-id');
        const csrf = $(this).data('csrf');
        stopProject(projectId, csrf);
    });
});

function sendForm(method, form, endpoint, event, refresh, successModal, errorModal){
    event.preventDefault();

    form.find(':required').each(function() {
        $(this).removeClass('is-invalid');
    });

    if (!form[0].checkValidity()) {
        form.find(":required").each(function() {
            if (!this.checkValidity()) {
                $(this).addClass("is-invalid");
            }
        });
        return;
    }

    const formData = new FormData(form[0]);

    $.ajax({
        type: method,
        dataType: 'json',
        data: formData,
        contentType: false,
        processData: false,
        url: endpoint,
        success: function(response) {
            if (!response.status || response.status === "success") {
                form[0].reset();

                if (refresh) {
                    location.reload();
                } else {
                    closeModal();
                    openModal(successModal);
                }

                return;
            }
            
            if (errorModal.length) {
                closeModal();
                openModal(errorModal);
                return;
            } else {
                alert('Error: ' + (response.message || 'Unknown error'));
                return;
            }
        },
        error: function(error) {
            if (errorModal.length) {
                closeModal();
                openModal(errorModal);
                return;
            } else {
                alert('Error: ' + error);
                return;
            }
        }
    });
}

function startProject(projectId, csrf) {
    const formData = new FormData();

    formData.append('action', 'start_project');
    formData.append('_csrf', csrf);
    formData.append('project_id', projectId);

    $.ajax({
        type: 'POST',
        dataType: 'json',
        data: formData,
        contentType: false,
        processData: false,
        url: '/ax_projects',
        success: function(response) {
            if(response.running){
                $('tr[data-project-id="' + projectId + '"] .stop-project').attr('disabled', false);
                $('tr[data-project-id="' + projectId + '"] .start-project').attr('disabled', true);
            } else {
                $('tr[data-project-id="' + projectId + '"] .start-project').attr('disabled', false);
                $('tr[data-project-id="' + projectId + '"] .stop-project').attr('disabled', true);
            }
        },
        error: function(error) {
            console.error(error);
        }
    })
}

function stopProject(projectId, csrf) {
    const formData = new FormData();

    formData.append('action', 'stop_project');
    formData.append('_csrf', csrf);
    formData.append('project_id', projectId);

    $.ajax({
        type: 'POST',
        dataType: 'json',
        data: formData,
        contentType: false,
        processData: false,
        url: '/ax_projects',
        success: function(response) {
            if(response.running){
                $('tr[data-project-id="' + projectId + '"] .stop-project').attr('disabled', false);
                $('tr[data-project-id="' + projectId + '"] .start-project').attr('disabled', true);
            } else {
                $('tr[data-project-id="' + projectId + '"] .start-project').attr('disabled', false);
                $('tr[data-project-id="' + projectId + '"] .stop-project').attr('disabled', true);
            }
        },
        error: function(error) {
            console.error(error);
        }
    })
}