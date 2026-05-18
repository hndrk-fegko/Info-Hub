const bootState = globalThis.V2ModuleBoot = globalThis.V2ModuleBoot || {
    status: 'idle',
    loadedModules: [],
    loadedScripts: [],
    error: null,
};

syncBootDiagnostics();

function syncBootDiagnostics() {
    const root = document.documentElement;
    if (!root) {
        return;
    }

    root.dataset.v2BootStatus = bootState.status || 'idle';
    root.dataset.v2BootModules = (bootState.loadedModules || []).map((entry) => entry.name).join(',');
    root.dataset.v2BootLegacyModules = (bootState.loadedScripts || []).map((entry) => entry.name).join(',');
    root.dataset.v2BootModuleUrls = (bootState.loadedModules || []).map((entry) => entry.url).join('|');
    root.dataset.v2BootLegacyUrls = (bootState.loadedScripts || []).map((entry) => entry.url).join('|');

    if (bootState.error) {
        root.dataset.v2BootError = String(bootState.error);
    } else {
        delete root.dataset.v2BootError;
    }
}

function getConfig() {
    return globalThis.V2_CONFIG || {};
}

function getScriptUrl(moduleName) {
    const config = getConfig();
    const scriptUrls = config.v2ScriptUrls || {};
    if (scriptUrls[moduleName]) {
        return new URL(scriptUrls[moduleName], document.baseURI).toString();
    }

    return new URL(`./${moduleName}.js`, import.meta.url).toString();
}

async function importCoreModule(moduleName) {
    const moduleUrl = getScriptUrl(moduleName);
    const namespace = await import(moduleUrl);

    bootState.loadedModules.push({
        name: moduleName,
        url: moduleUrl,
        exports: Object.keys(namespace),
    });

    syncBootDiagnostics();

    return namespace;
}

function loadLegacyScript(moduleName) {
    const scriptUrl = getScriptUrl(moduleName);

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = scriptUrl;
        script.async = false;
        script.dataset.v2LegacyModule = moduleName;
        script.onload = () => {
            bootState.loadedScripts.push({ name: moduleName, url: scriptUrl });
            syncBootDiagnostics();
            resolve();
        };
        script.onerror = () => reject(new Error(`Failed to load ${moduleName} from ${scriptUrl}`));
        document.body.appendChild(script);
    });
}

async function boot() {
    if (bootState.status === 'loading' || bootState.status === 'ready') {
        return;
    }

    bootState.status = 'loading';
    syncBootDiagnostics();

    try {
        await importCoreModule('state');
        await importCoreModule('api-client');

        await importCoreModule('media-picker');

        await importCoreModule('insert');
        await importCoreModule('drag-drop');

        await importCoreModule('edit-modal');

        await importCoreModule('settings');
        await importCoreModule('context-menu');

        await importCoreModule('canvas');

        bootState.status = 'ready';
        syncBootDiagnostics();
        document.dispatchEvent(new CustomEvent('v2:boot-ready', { detail: bootState }));
    } catch (error) {
        bootState.status = 'error';
        bootState.error = error instanceof Error ? (error.stack || error.message) : String(error);
        syncBootDiagnostics();
        console.error('[V2Boot] Failed to load V2 editor assets', error);
    }
}

boot();