/*
| Progressive enhancement for the sidebar disclosures.
|
| The project and version selectors are `<details>` elements, so they open,
| close, and answer the keyboard with no JavaScript at all — which is the
| baseline this has to preserve. What the browser does not give them is the one
| behaviour people expect of a menu: closing when you look away.
|
| So this adds exactly that, and nothing else. With the script blocked or still
| loading, the selectors keep working; they just need a second click on their
| own summary to close.
*/
const selectors = () => document.querySelectorAll('details[data-dismissible]');

document.addEventListener('click', (event) => {
    selectors().forEach((details) => {
        if (details.open && !details.contains(event.target)) {
            details.open = false;
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    selectors().forEach((details) => {
        if (details.open) {
            details.open = false;
            details.querySelector('summary')?.focus();
        }
    });
});

// One open at a time: two overlapping menus in a 14rem column is a mess.
document.addEventListener('toggle', (event) => {
    const opened = event.target;

    if (!opened.matches?.('details[data-dismissible]') || !opened.open) {
        return;
    }

    selectors().forEach((details) => {
        if (details !== opened) {
            details.open = false;
        }
    });
}, true);

/*
| Copy controls and language labels on fenced code.
|
| Added here rather than by the renderer: Phiki owns tokenization and the markup
| inside a fence, and the toolbar around it is the application's.
| Doing it in the browser also keeps the rendered HTML — which is cached, and
| which a static export would emit — free of controls that do nothing without
| JavaScript.
*/
/*
| Phiki names grammars, not languages: a ```shell fence is tokenized by the
| `shellscript` grammar and would otherwise be labelled that. These map the
| common grammars to the names the documentation writes them as.
*/
const LANGUAGE_LABELS = {
    shellscript: 'shell',
    bash: 'shell',
    sh: 'shell',
    php: 'PHP',
    blade: 'Blade',
    css: 'CSS',
    yaml: 'YAML',
    yml: 'YAML',
    json: 'JSON',
};

const decorateCodeBlocks = () => {
    document.querySelectorAll('.docs-prose pre.phiki:not([data-decorated])').forEach((pre) => {
        pre.dataset.decorated = 'true';

        const toolbar = document.createElement('div');
        toolbar.className = 'code-toolbar';

        const language = pre.dataset.language;

        if (language && language !== 'text' && language !== 'plaintext') {
            const label = document.createElement('span');
            label.className = 'code-language';
            label.textContent = LANGUAGE_LABELS[language] ?? language;
            toolbar.append(label);
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'code-copy';
        button.textContent = 'Copy';

        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(pre.innerText);
                button.textContent = 'Copied';
            } catch {
                // Denied permission, or an insecure origin. Saying so beats a
                // button that silently did nothing.
                button.textContent = 'Press ⌘C';
            }

            setTimeout(() => { button.textContent = 'Copy'; }, 2000);
        });

        toolbar.append(button);
        pre.prepend(toolbar);
    });
};

decorateCodeBlocks();
document.addEventListener('livewire:navigated', decorateCodeBlocks);
