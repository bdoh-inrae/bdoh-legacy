define([
    'jquery',
    'jquery-form/jquery.form'
], ($) => {

    $.measureImport = {
        id: '#measure-import',

        // Data received from the server
        data: {},

        // Initializes a new step
        init() {
            this.main = $(this.id);
            this.form = this.main.find('form[name = "import"]');
            return this;
        },

        // Gets a field DOM element
        field(value, attr) {
            attr = attr || 'name';
            return this.form.find(`[${attr}="${value}"]`);
        },

        // Initializes the submit button and returns its DOM element
        submit() {
            const submitButton = this.form.find('[type = "submit"]');

            // Methods
            submitButton.enable = function () {
                return $(this).removeAttr('disabled')
                    .addClass('btn-success');
            };

            submitButton.disable = function () {
                return $(this).attr('disabled', true)
                    .removeClass('btn-success');
            };

            // Events
            this.form.submit(() => {
                submitButton.button('loading');
            });

            return submitButton;
        },

        // Initializes the button "Cancel measures import" and returns its DOM element.
        cancel() {
            const cancelForm = this.main.find('form[name = "cancel"]');
            const cancelButton = cancelForm.children('[type = "submit"]');
            const submit = this.submit();

            // Events
            this.form.submit(() => {
                cancelButton.attr('disabled', true);
            });

            cancelForm.submit(() => {
                cancelButton.button('loading');
                submit.disable();
            });

            return cancelButton;
        },

        /**
         * Builds a closure which enables or disables the submit button.
         * Runs once this closure and returns it.
         * @param {Closure}  test  Must return true to enable, false to disable
         */
        stateOfSubmit(test) {
            const submit = this.submit();
            const stateOfSubmit = function () {
                if (test()) {
                    submit.enable();
                } else {
                    submit.disable();
                }
            };
            stateOfSubmit();
            return stateOfSubmit;
        },

        /**
         * Depending of 'data.state' :
         *  => displays errors and format help ;
         *  => OR displays the next step and calls its function
         *
         * @param {Closure}  nextStep  Calls the next step function
         */
        nextStep(data, nextStep) {
            if (data.action === 'errors') {
                this.cancel().remove();
                this.form.replaceWith(data.view);
                $('#format-description').children(`ul[name  = "${data.idImporter}"]`)
                    .show(0);
            } else {
                this.main.empty().append(data.view);
                this.data = data;
                nextStep();
            }
        }
    };

    $.measureExport = {
        id: '#measure-import',

        // Initializes a new step
        init() {
            this.main = $(this.id);
            this.form = this.main.find('form[name = "export"]');
            return this;
        },

        // Gets a field DOM element
        field(value, attr) {
            attr = attr || 'name';
            return this.form.find(`[${attr}="${value}"]`);
        },

        // Initializes the submit button and returns its DOM element
        submit() {
            const submitButton = this.form.find('[type = "submit"]');

            // Methods
            submitButton.enable = function () {
                submitButton.button('reset');
                return $(this).removeAttr('disabled')
                    .addClass('btn-success');
            };

            submitButton.disable = function () {
                submitButton.button('reset');
                return $(this).attr('disabled', true)
                    .removeClass('btn-success');
            };

            return submitButton;
        },

        /**
         * Builds a closure which enables or disables the submit button.
         * Runs once this closure and returns it.
         * @param {Closure}  test  Must return true to enable, false to disable
         */
        stateOfSubmit(test) {
            const submit = this.submit();
            const stateOfSubmit = function () {
                if (test()) {
                    submit.enable();
                } else {
                    submit.disable();
                }
            };
            stateOfSubmit();
            return stateOfSubmit;
        }
    };

    $(() => {
        $.measureImport.step1();
        $.measureExport.step1();
    });

});
