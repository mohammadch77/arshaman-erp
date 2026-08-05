import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Quote from '@editorjs/quote';
import ImageTool from '@editorjs/image';

const editorInstances = {};

window.initBlogEditor = function (elementId, initialBlocks, inputElementId, imageUploadUrl) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const editor = new EditorJS({
        holder: elementId,
        minHeight: 0,
        data: {
            blocks: Array.isArray(initialBlocks) ? initialBlocks : [],
        },
        tools: {
            header: Header,
            list: {
                class: List,
                inlineToolbar: true,
            },
            quote: {
                class: Quote,
                inlineToolbar: true,
            },
            image: {
                class: ImageTool,
                config: {
                    uploader: {
                        async uploadByFile(file) {
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

                            return response.json();
                        },
                    },
                },
            },
        },
    });

    editorInstances[elementId] = { editor, inputElementId };
};

window.saveBlogEditor = function (elementId) {
    const instance = editorInstances[elementId];

    if (! instance) {
        return Promise.resolve();
    }

    return instance.editor.save().then((outputData) => {
        const input = document.getElementById(instance.inputElementId);

        if (input) {
            input.value = JSON.stringify(outputData.blocks);
            input.dispatchEvent(new Event('input'));
        }
    });
};
