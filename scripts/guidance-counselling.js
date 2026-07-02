document.addEventListener('DOMContentLoaded', function () {
    var studentDirectory = document.querySelector('[data-student-directory]');
    if (studentDirectory) {
        var filterInput = studentDirectory.querySelector('[data-student-filter]');
        var studentSelect = studentDirectory.querySelector('[data-student-select]');
        var statusNode = studentDirectory.querySelector('[data-student-picker-status]');
        var classFilter = studentDirectory.querySelector('[data-student-class-filter]');
        var batchFilter = studentDirectory.querySelector('[data-student-batch-filter]');
        var scopeLabel = studentDirectory.getAttribute('data-student-scope-label') || 'students in your counselling scope';
        var placeholderLabel = studentDirectory.getAttribute('data-student-placeholder') || 'Select Student From List';

        if (filterInput && studentSelect) {
            var sourceOptions = Array.prototype.slice.call(studentSelect.querySelectorAll('option')).slice(1).map(function (option) {
                return {
                    value: option.value,
                    text: option.text,
                    search: (option.getAttribute('data-search') || option.text || '').toLowerCase(),
                    className: (option.getAttribute('data-class') || '').toLowerCase(),
                    batchName: (option.getAttribute('data-batch') || '').toLowerCase()
                };
            });
            var totalStudents = sourceOptions.length;

            var renderStudentOptions = function () {
                var query = filterInput.value.trim().toLowerCase();
                var classValue = classFilter ? classFilter.value.trim().toLowerCase() : '';
                var batchValue = batchFilter ? batchFilter.value.trim().toLowerCase() : '';
                var currentValue = studentSelect.value;
                var matches = sourceOptions.filter(function (option) {
                    var matchesQuery = query === '' || option.search.indexOf(query) !== -1;
                    var matchesClass = classValue === '' || option.className === classValue;
                    var matchesBatch = batchValue === '' || option.batchName === batchValue;
                    return matchesQuery && matchesClass && matchesBatch;
                });

                studentSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = matches.length ? placeholderLabel : 'No student found';
                studentSelect.appendChild(placeholder);

                matches.forEach(function (option) {
                    var optionNode = document.createElement('option');
                    optionNode.value = option.value;
                    optionNode.textContent = option.text;
                    optionNode.setAttribute('data-search', option.search);
                    if (option.value === currentValue) {
                        optionNode.selected = true;
                    }
                    studentSelect.appendChild(optionNode);
                });

                if (statusNode) {
                    statusNode.textContent = 'Showing ' + matches.length + ' of ' + totalStudents + ' ' + scopeLabel + '.';
                }
            };

            filterInput.addEventListener('input', renderStudentOptions);
            if (classFilter) {
                classFilter.addEventListener('change', renderStudentOptions);
            }
            if (batchFilter) {
                batchFilter.addEventListener('change', renderStudentOptions);
            }
            renderStudentOptions();
        }
    }

    var pageSelects = document.querySelectorAll('.gc-field select');
    if (pageSelects.length) {
        var updateSelectState = function (select) {
            select.classList.toggle('is-empty', select.value === '');
        };

        pageSelects.forEach(function (select) {
            select.addEventListener('change', function () {
                updateSelectState(select);
            });
            updateSelectState(select);
        });
    }

    var forms = document.querySelectorAll('[data-action-form]');
    if (forms.length) {
        forms.forEach(function (form) {
            var actionSelect = form.querySelector('[data-action-select]');
            var actionNote = form.querySelector('[data-action-note]');
            var submitButton = form.querySelector('[data-action-submit]');
            var rescheduleFields = form.querySelectorAll('[data-reschedule-only] input, [data-reschedule-only] select, [data-reschedule-only] textarea');

            if (!actionSelect || !actionNote || !submitButton) {
                return;
            }

            var updateState = function () {
                var isCancel = actionSelect.value === 'cancel';
                form.classList.toggle('is-cancel-mode', isCancel);

                rescheduleFields.forEach(function (field) {
                    field.disabled = isCancel;
                });

                if (isCancel) {
                    actionNote.innerHTML = 'You are about to <strong>cancel this appointment</strong>. A new date and time are not required.';
                    submitButton.classList.remove('gc-btn--secondary');
                    submitButton.classList.add('gc-btn--danger');
                    submitButton.innerHTML = '<i class="fa fa-times-circle"></i> Cancel Appointment';
                    return;
                }

                actionNote.innerHTML = 'Choose <strong>Request Another Day</strong> to suggest a new meeting time. Choose <strong>Cancel This Appointment</strong> to close this appointment.';
                submitButton.classList.remove('gc-btn--danger');
                submitButton.classList.add('gc-btn--secondary');
                submitButton.innerHTML = '<i class="fa fa-calendar-times-o"></i> Send Appointment Request';
            };

            actionSelect.addEventListener('change', updateState);
            updateState();
        });
    }
});
