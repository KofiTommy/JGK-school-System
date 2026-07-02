document.addEventListener('DOMContentLoaded', function () {
    var selects = document.querySelectorAll('.ca-field select');
    if (!selects.length) {
        return;
    }

    var updateSelectState = function (select) {
        select.classList.toggle('is-empty', select.value === '');
    };

    selects.forEach(function (select) {
        select.addEventListener('change', function () {
            updateSelectState(select);
        });
        updateSelectState(select);
    });
});
