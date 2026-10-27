const $ = require('jquery');

$.shapeImport.step2 = function () {
  const shapeImport = $.shapeImport.init();
  const form = shapeImport.form;
  const importTypes = shapeImport.form.find('input[type = "radio"]');
  const stateOfSubmit = shapeImport.stateOfSubmit(() => {
    return importTypes.filter(':checked').length !== 0;
  });

    // Enables or not the "submit button"
    importTypes.on('click', stateOfSubmit);

    // Send 'importType' to the server
    form.ajaxForm({
        dataType : 'json',
        success (data) {
            shapeImport.nextStep(data, () => {});
        }
    });
};
