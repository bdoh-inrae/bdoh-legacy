const $ = require('jquery');

$.shapeImport.step1 = function () {
  const shapeImport = $.shapeImport.init();
  const formatDescription = $('#format-description');
  const form = shapeImport.form;
  const file = shapeImport.field('file');
  const fileShape = shapeImport.field('file-shape');
  const stateOfSubmit = shapeImport.stateOfSubmit(() => {
    return (file.val() !== '' && fileShape.val() !== '');
  });

  // When a format is selected, displays its description
  fileShape.on('change', function () {
    const id = $(':selected', this).val();
    const value = $(':selected', this).text();


    if (id === '') {
      // Hides description
      formatDescription.children('h4, ul').hide(0);
    }
    else {
      // Shows the good description
      formatDescription.find('h4 > i').html(value);
      formatDescription.children(`ul[name != "${id}"]`).hide(0);
      formatDescription.children(`ul[name  = "${id}"], h4`).show(0);
    }
  });
  // Enables or not the "submit button"
  file.add(fileShape).on('change', stateOfSubmit);

  // Sends file and file format to the server
  form.ajaxForm({
    dataType: 'json',
    success(data) {
      const nextStep = shapeImport.step2;
      shapeImport.nextStep(data, nextStep);
    }
  });
};

