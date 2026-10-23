(function () {
    const config = window.watAdmin;
    if (!config) {
        return;
    }

    async function call(action, fields) {
        const body = new URLSearchParams(Object.assign({
            action: action,
            _ajax_nonce: config.nonce,
        }, fields || {}));
        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
        });
        const json = await response.json();
        if (!json.success) {
            const message = json.data && json.data.message ? json.data.message : 'Request failed';
            throw new Error(message);
        }
        return json.data;
    }

    const progress = document.getElementById('wat-progress');
    const run = document.getElementById('wat-translate-all');
    if (run && progress) {
        run.addEventListener('click', async function () {
            run.disabled = true;
            progress.textContent = 'Looking for posts to translate…';
            try {
                let after = 0;
                for (let guard = 0; guard < 200; guard += 1) {
                    const batch = await call('wat_enqueue_batch', { after: String(after) });
                    after = batch.lastId;
                    progress.textContent = batch.pending + ' queued.';
                    if (batch.done) {
                        break;
                    }
                }

                const workers = Math.max(1, Number(config.workers) || 4);
                let failures = 0;
                async function drain() {
                    try {
                        for (;;) {
                            const result = await call('wat_process_next');
                            if (!result.processed) {
                                return;
                            }
                            const detail = result.error ? result.error : ('Translated to ' + result.locale);
                            progress.textContent = detail + '. ' + result.remaining + ' left.';
                        }
                    } catch (error) {
                        failures += 1;
                        progress.textContent = error.message;
                    }
                }
                await Promise.all(Array.from({ length: workers }, drain));
                if (failures === 0) {
                    progress.textContent = 'Queue finished.';
                }
            } catch (error) {
                progress.textContent = error.message;
            } finally {
                run.disabled = false;
            }
        });
    }

    const test = document.getElementById('wat-test-api');
    const testResult = document.getElementById('wat-test-result');
    if (test && testResult) {
        test.addEventListener('click', async function () {
            test.disabled = true;
            testResult.textContent = 'Checking the saved key…';
            try {
                const result = await call('wat_test_api');
                testResult.textContent = result.message;
            } catch (error) {
                testResult.textContent = error.message;
            } finally {
                test.disabled = false;
            }
        });
    }
}());
