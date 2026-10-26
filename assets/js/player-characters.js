(() => {
    'use strict';
    document.querySelectorAll('[data-character-editor]').forEach(editor => {
        const preview = editor.querySelector('[data-character-preview]');
        const summary = editor.querySelector('[data-character-summary]');
        const tabs = [...editor.querySelectorAll('[data-character-tab]')];
        const panels = tabs.map(tab => document.getElementById(tab.dataset.characterTab));
        const random = editor.querySelector('[data-character-random]');
        const characters = [...editor.querySelectorAll('[name="character_id"]')];
        const accessories = [...editor.querySelectorAll('[name="accessory_id"]')];

        function updatePreview() {
            const character = characters.find(input => input.checked);
            const accessory = accessories.find(input => input.checked);
            if (!character || !accessory) return;
            const svg = character.closest('label').querySelector('svg').cloneNode(true);
            svg.classList.add('character-preview-avatar');
            const layer = svg.querySelector('.avatar-accessory');
            layer.replaceChildren();
            if (accessory.value) {
                const image = document.createElementNS('http://www.w3.org/2000/svg', 'image');
                image.setAttribute('href', accessory.dataset.src);
                image.setAttribute('width', '100');
                image.setAttribute('height', '100');
                layer.append(image);
            }
            const label = character.dataset.name + (accessory.value ? ' with ' + accessory.dataset.name : '');
            svg.setAttribute('aria-label', label);
            preview.replaceChildren(svg);
            summary.textContent = label;
        }

        function selectTab(index, focus = false) {
            tabs.forEach((tab, i) => {
                tab.setAttribute('aria-selected', String(i === index));
                tab.tabIndex = i === index ? 0 : -1;
                panels[i].hidden = i !== index;
            });
            if (focus) tabs[index].focus();
        }
        editor.querySelector('[role="tablist"]').hidden = false;
        panels.forEach((panel, i) => {
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tabs[i].id);
        });
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => selectTab(index));
            tab.addEventListener('keydown', event => {
                if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                    event.preventDefault();
                    selectTab(event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + 1) % tabs.length, true);
                }
            });
        });
        editor.addEventListener('change', updatePreview);
        random.hidden = false;
        random.addEventListener('click', () => {
            characters[Math.floor(Math.random() * characters.length)].checked = true;
            accessories[Math.floor(Math.random() * accessories.length)].checked = true;
            updatePreview();
        });
        selectTab(0);
    });

    const form = document.getElementById('playerJoinForm');
    if (!form) return;
    const nickname = form.querySelector('[name="nickname"]');
    const nameStep = document.getElementById('nicknameStep');
    const appearanceStep = document.getElementById('appearanceStep');
    const next = document.getElementById('joinContinue');
    const back = document.getElementById('joinBack');
    const submit = document.getElementById('joinSubmit');
    const heading = document.getElementById('joinHeading');
    const description = document.getElementById('joinDescription');
    const steps = document.getElementById('joinStepLabel');
    let editing = false;
    function showAppearance(show, focus = true) {
        editing = show;
        nameStep.hidden = show;
        appearanceStep.hidden = !show;
        next.hidden = show;
        back.hidden = !show;
        submit.hidden = !show;
        heading.textContent = show ? 'Make it yours!' : 'Enter your nickname';
        description.textContent = show ? 'Pick a character, add an accessory, and get ready to play.' : 'This is how you will appear on the big screen.';
        steps.textContent = show ? '2 / 2 · Your character' : '1 / 2 · Your nickname';
        if (focus) (show ? document.getElementById('charactersTab') : nickname).focus();
    }
    function continueJoin() {
        nickname.value = nickname.value.trim();
        if (nickname.reportValidity()) showAppearance(true);
    }
    next.addEventListener('click', continueJoin);
    back.addEventListener('click', () => showAppearance(false));
    form.addEventListener('submit', event => {
        if (!editing) {
            event.preventDefault();
            continueJoin();
        }
    });
    steps.hidden = false;
    showAppearance(form.dataset.hasError === 'true' && nickname.value.trim() !== '', false);
})();
