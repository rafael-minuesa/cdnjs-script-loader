document.addEventListener('DOMContentLoaded', function() {
    var addButton = document.getElementById('add-script-field');
    var fieldsContainer = document.getElementById('script-fields');

    addButton.addEventListener('click', function() {
        var newField = document.createElement('div');
        newField.className = 'script-field';
        newField.innerHTML = '<input type="text" name="cdnjs_script_loader_settings[scripts][]" placeholder="Script Name (e.g., jquery)" />' +
                             '<input type="text" name="cdnjs_script_loader_settings[versions][]" placeholder="Version (e.g., 3.5.1)" />';
        fieldsContainer.appendChild(newField);
    });
});
