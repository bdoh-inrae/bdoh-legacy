// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
const $ = require('jquery');

require('@IrsteaBdohBundle/lib/date-time/range-picker');
require('@IrsteaBdohBundle/lib/datatables');

$(() => $('.table').DataTable());
