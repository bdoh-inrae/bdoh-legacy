// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
const $ = require('jquery');
require('@IrsteaBdohBundle/lib/datatables');

$(() => $('#jobs').DataTable({/*'pageLength':25, */'order': [[0, 'desc']]}));
