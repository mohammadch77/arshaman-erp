import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const editorInstances = {};

function uploadImage(quill, imageUploadUrl, csrfToken) {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');

    input.onchange = async () => {
        const file = input.files[0];

        if (! file) {
            return;
        }

        const range = quill.getSelection(true);

        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch(imageUploadUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: formData,
        });

        const json = await response.json();

        if (json.file && json.file.url) {
            quill.insertEmbed(range.index, 'image', json.file.url, 'user');
            quill.setSelection(range.index + 1);
        }
    };

    input.click();
}

window.initBlogEditor = function (elementId, initialHtml, inputElementId, imageUploadUrl) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const quill = new Quill('#'+elementId, {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ header: [1, 2, 3, 4, 5, 6, false] }],
                    ['link', 'image'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote'],
                ],
                handlers: {
                    image() {
                        uploadImage(quill, imageUploadUrl, csrfToken);
                    },
                },
            },
        },
    });

    if (initialHtml) {
        quill.clipboard.dangerouslyPasteHTML(initialHtml);
    }

    const syncToInput = () => {
        const input = document.getElementById(inputElementId);

        if (input) {
            input.value = quill.root.innerHTML;
            input.dispatchEvent(new Event('input'));
        }
    };

    quill.on('text-change', syncToInput);

    editorInstances[elementId] = { quill, inputElementId, syncToInput };
};

window.saveBlogEditor = function (elementId) {
    const instance = editorInstances[elementId];

    if (! instance) {
        return Promise.resolve();
    }

    instance.syncToInput();

    return Promise.resolve();
};
