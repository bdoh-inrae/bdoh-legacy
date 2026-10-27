define([
    'jquery',
    './import'
], ($) => {

    $.baremeImport = {
        id: '#bareme-import',

        // Data received from the server
        data: {},

        // Initializes a new step
        init() {
            this.main = $(this.id);
            this.form = this.main.find('form[name = "form-bareme-import"]');
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

        // Initializes the button "Cancel scale import" and returns its DOM element.
        cancel() {
            const cancelButton = this.main.find('a.btn-danger');

            cancelButton.on('click', (e) => {
                e.preventDefault();
                this.form.attr('action', $(e.target).attr('href'));
                this.form.ajaxSubmit();
                this.main.modal('hide');
            });

            return cancelButton;
        },

        // Initializes the button "Reinitiate the import" and returns its DOM element.
        reInit() {
            const reInitButton = this.main.find('a.btn-default');

            reInitButton.on('click', () => {
              this.main.modal('show');
            });

          return reInitButton;
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
                this.reInit();
            } else {
                this.main.empty().append(data.view);
                this.data = data;
                nextStep();
            }
        }
    };

});
