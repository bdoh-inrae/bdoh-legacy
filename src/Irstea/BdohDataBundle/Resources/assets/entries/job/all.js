const $ = require('jquery');
require('@IrsteaBdohBundle/lib/datatables');

$(() => $('#jobs').DataTable({/*'pageLength':25, */'order': [[0, 'desc']]}));
