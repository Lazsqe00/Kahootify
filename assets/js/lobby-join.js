(function () {
    'use strict';

    const lobby = document.getElementById('lobbyJoin');
    if (!lobby) return;

    const joinUrl = lobby.dataset.joinUrl;
    const qrContainer = document.getElementById('joinQrCode');
    const enlargeButton = document.getElementById('enlargeJoinQr');
    const dialog = document.getElementById('joinQrDialog');

    try {
        const qr = qrcodegen.QrCode.encodeText(joinUrl, qrcodegen.QrCode.Ecc.MEDIUM);
        const border = 4; // Keep a clear, four-module quiet zone for phone cameras.
        const size = qr.size + border * 2;
        const namespace = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(namespace, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
        svg.setAttribute('shape-rendering', 'crispEdges');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'QR code to join this game');

        const background = document.createElementNS(namespace, 'rect');
        background.setAttribute('width', '100%');
        background.setAttribute('height', '100%');
        background.setAttribute('fill', '#fff');
        svg.appendChild(background);

        const modules = [];
        for (let y = 0; y < qr.size; y++) {
            for (let x = 0; x < qr.size; x++) {
                if (qr.getModule(x, y)) {
                    modules.push('M' + (x + border) + ',' + (y + border) + 'h1v1h-1z');
                }
            }
        }
        const path = document.createElementNS(namespace, 'path');
        path.setAttribute('d', modules.join(''));
        path.setAttribute('fill', '#000');
        svg.appendChild(path);
        qrContainer.replaceChildren(svg);
        document.getElementById('joinQrCodeLarge').replaceChildren(svg.cloneNode(true));

        if (typeof dialog.showModal === 'function') {
            enlargeButton.disabled = false;
            document.getElementById('joinQrHint').textContent = 'Click QR code to enlarge';
            enlargeButton.addEventListener('click', function () { dialog.showModal(); });
            dialog.addEventListener('click', function (event) {
                const bounds = dialog.getBoundingClientRect();
                if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                    dialog.close();
                }
            });
        }
    } catch (error) {
        qrContainer.textContent = 'QR code unavailable. Please join using the PIN.';
        document.getElementById('joinQrHint').textContent = '';
    }

    const copyButton = document.getElementById('copyJoinLink');
    copyButton.hidden = false;
    copyButton.addEventListener('click', async function () {
        const status = document.getElementById('joinLinkStatus');
        const fallback = document.getElementById('joinLinkFallback');
        try {
            await navigator.clipboard.writeText(joinUrl);
            fallback.hidden = true;
            status.textContent = 'Join link copied!';
        } catch (error) {
            // Clipboard API may be unavailable on HTTP LAN addresses.
            fallback.hidden = false;
            fallback.focus();
            fallback.select();
            fallback.setSelectionRange(0, fallback.value.length);
            status.textContent = 'Select and copy the join link below.';
        }
    });
})();
