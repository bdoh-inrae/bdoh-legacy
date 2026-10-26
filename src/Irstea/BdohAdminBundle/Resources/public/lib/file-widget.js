// Le champ des fichiers joints (FileType, Form/widgets.html.twig : bdoh_file_widget), sur le jQuery
// de Sonata. Repris de Resources/assets/lib/file-widget/ de l'ancien BDOH, en un seul fichier.
(function ($) {

    class BaseWidget {

        constructor(el) {

            this.element = $(el);
            this.basePath = this.element.data('base-path');

            this.fileInput = this.element.find('[name$="[new]"]');
            this.currentInput = this.element.find('[name$="[current]"]');

            this.preview = this.element.find('.preview');
            this.placeholder = this.element.find('.placeholder');
            this.deleteBtn = this.element.find('.delete');
            this.undoBtn = this.element.find('.undo');

            this.initialValue = this.currentInput.val() || '';

            this.undoBtn.on('click.bdoh-file-widget', (ev) => {
                ev.preventDefault();
                this.undo();
            });

            this.deleteBtn.on('click.bdoh-file-widget', (ev) => {
                ev.preventDefault();
                this.delete();
            });

            this.fileInput.on('change.bdoh-file-widget', () => this.update());
        }

        undo() {
            this.fileInput.val('');
            this.currentInput.val(this.initialValue);
            this.update();
        }

        delete() {
            this.fileInput.val('');
            this.currentInput.val('');
            this.update();
        }

        update() {
            const currentValue = this.currentInput.val() || '';
            const newValue = this.fileInput.val() || '';
            const anyValue = newValue || currentValue;
            const hasValue = anyValue !== '';

            this.undoBtn.toggle(anyValue !== this.initialValue && this.initialValue !== '');

            this.deleteBtn.toggle(hasValue);
            this.preview.toggle(hasValue);
            this.placeholder.toggle(!hasValue);

            if (newValue) {
                this.showNewPreview(this.fileInput[0].files[0]);
            } else if (currentValue) {
                this.showCurrentPreview(this.basePath + currentValue);
            }
        }

        showNewPreview() {
            throw new Error('Abstract method AbstractFileWidget.showNewPreview');
        }

        showCurrentPreview() {
            throw new Error('Abstract method AbstractFileWidget.showNewPreview');
        }
    }

    class FileWidget extends BaseWidget {

        constructor(el) {
            super(el);

            this.link = this.preview.find('a');
            this.linkText = this.link.find('span');
        }

        showNewPreview() {
            this.preview.hide();
        }

        showCurrentPreview(url) {
            const lastSlash = url.lastIndexOf('/');
            const name = lastSlash >= 0 ? url.substr(lastSlash + 1) : url;
            this.link.attr('href', url);
            this.linkText.text(name);
        }
    }

    class ImageWidget extends BaseWidget {

        showNewPreview(file) {
            const reader = new FileReader();
            reader.onloadend = () => this.preview.attr('src', reader.result);
            reader.readAsDataURL(file);
        }

        showCurrentPreview(url) {
            this.preview.attr('src', url);
        }
    }

    const classes = {
        file: FileWidget,
        image: ImageWidget,
    };

    function enableFileWidget(el) {
        const type = $(el).data('file-widget');
        const clazz = classes[type];
        if (clazz) {
            const widget = new clazz(el);
            widget.update();
        }
    }

    window.enableFileWidget = function (selector) {
        $(() => {
            enableFileWidget($(selector));
        });
    };

})(jQuery);
