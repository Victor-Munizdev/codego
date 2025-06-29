<!-- Container do editor visual -->
<div id="editor-container" style="height: 300px; width: 100%; border: 1px solid #ccc; border-radius: 5px;"></div>
<textarea name="resposta" id="resposta" style="display: none;"></textarea>

<!-- Ace Editor via CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.3/ace.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.3/ext-language_tools.js"></script>

<script>
    const editor = ace.edit("editor-container");
    editor.setTheme("ace/theme/monokai");

    editor.session.setMode("ace/mode/html");

    editor.setOptions({
        fontSize: "16px",
        showPrintMargin: false,
        wrap: true,
        useWorker: false,
        highlightActiveLine: true,

        // Ativa autocompletion básica e snippets
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true
    });

    editor.setReadOnly(false);

    // Sincroniza com textarea oculta ao enviar
    document.querySelector("form").addEventListener("submit", function () {
        document.getElementById("resposta").value = editor.getValue();
    });

    // Foca editor ao clicar no container
    document.getElementById("editor-container").addEventListener("click", () => {
        editor.focus();
    });
</script>
