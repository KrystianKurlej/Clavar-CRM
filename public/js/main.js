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
        startProject(projectId);
    });

    $('.stop-project').on('click', function() {
        const projectId = $(this).data('project-id');
        stopProject(projectId);
    });

    $('.delete-modal-btn').on('click', function() {
        const modalTitle = $(this).data('modal-title');
        const itemId = $(this).data('item-id');
        
        $('#deleteModalTitle').text(modalTitle);
        $('#confirmDeleteModal input[name="id"]').val(itemId);
    });
    
    $('.edit-project-modal-btn').on('click', function() {
        const projectTitle = $(this).data('project-title');
        const itemId = $(this).data('item-id');
        
        $('#editModalTitle').text(projectTitle);
        $('#editProjectName').val(projectTitle);
        $('#editProjectModal input[name="id"]').val(itemId);
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

function updateProject(projectId, isRunning, time){
    const tableRow = $('tr[data-project-id="' + projectId + '"]');

    if (isRunning) {
        tableRow.find('.stop-project').attr('disabled', false);
        tableRow.find('.start-project').attr('disabled', true);
        tableRow.find('.badge.text-bg-success').show();
    } else {
        tableRow.find('.start-project').attr('disabled', false);
        tableRow.find('.stop-project').attr('disabled', true);
        tableRow.find('.badge.text-bg-success').hide();
    }

    tableRow.find('.project-time').text(time);
}

function startProject(projectId) {
    const formData = new FormData();
    const csrf = $('.projects-table').data('csrf');

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
            updateProject(projectId, response.running, response.total);
        },
        error: function(error) {
            console.error(error);
        }
    })
}

function stopProject(projectId) {
    const formData = new FormData();
    const csrf = $('.projects-table').data('csrf');

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
            updateProject(projectId, response.running, response.total);
        },
        error: function(error) {
            console.error(error);
        }
    })
}