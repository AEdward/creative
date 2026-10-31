const mediaText = (message) => {
    const element = document.getElementById("media-localization");
    const messages = element ? JSON.parse(element.dataset.messages) : {};
    return messages[message] ?? message;
};
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
async function api(url, body) {
    const response = await fetch(url, {
        method: body ? "POST" : "GET",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf(),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const data = await response.json();
    if (!response.ok)
        throw new Error(
            Object.values(data.errors || {}).flat()[0] ||
                data.message ||
                mediaText("Please retry later."),
        );
    return data;
}
function initializeMedia() {
    document
        .querySelectorAll("[data-private-video]:not([data-initialized])")
        .forEach((root) => {
            root.dataset.initialized = "true";
            const video = root.querySelector("video");
            const status = root.querySelector("[data-video-status]");
            let resume = Number(root.dataset.resume || 0),
                lastSave = 0,
                loading = false;
            const load = async () => {
                if (loading) return;
                loading = true;
                resume = video.currentTime || resume;
                try {
                    const data = await api(root.dataset.playback);
                    video.src = data.url;
                    status.textContent = mediaText("Ready to watch.");
                } catch {
                    status.textContent =
                        mediaText("Playback is unavailable. Refresh playback to retry.");
                } finally {
                    loading = false;
                }
            };
            video.addEventListener("loadedmetadata", () => {
                if (resume > 0 && Number.isFinite(video.duration))
                    video.currentTime = Math.min(
                        resume,
                        Math.max(0, video.duration - 1),
                    );
            });
            video.addEventListener("error", () => {
                status.textContent =
                    mediaText("Playback stopped or the link expired. Refresh playback to continue from your place.");
            });
            video.addEventListener("timeupdate", async () => {
                if (!root.dataset.progress || Date.now() - lastSave < 15000)
                    return;
                lastSave = Date.now();
                try {
                    await api(root.dataset.progress, {
                        position_seconds: Math.floor(video.currentTime),
                    });
                } catch {
                    status.textContent =
                        mediaText("Your place could not be saved. Check your connection.");
                }
            });
            root.querySelector("[data-reload-video]").addEventListener(
                "click",
                load,
            );
            load();
        });
    document
        .querySelectorAll("[data-video-upload]:not([data-initialized])")
        .forEach((form) => {
            form.dataset.initialized = "true";
            const status = form.querySelector('[role="status"]');
            const submit = form.querySelector('[type="submit"]');
            let xhr;
            form.querySelector("[data-cancel-upload]").addEventListener(
                "click",
                () => xhr?.abort(),
            );
            form.addEventListener("submit", async (event) => {
                event.preventDefault();
                const file = form.querySelector('[type="file"]').files[0];
                if (
                    !file ||
                    !file.name.toLowerCase().endsWith(".mp4") ||
                    file.size > 1073741824
                ) {
                    status.textContent = mediaText("Choose an MP4 video up to 1 GB.");
                    return;
                }
                submit.disabled = true;
                try {
                    const intent = await api(form.dataset.videoUpload, {
                        filename: file.name,
                        bytes: file.size,
                        mime: "video/mp4",
                    });
                    status.textContent =
                        mediaText("Uploading directly to private storage…");
                    await new Promise((resolve, reject) => {
                        xhr = new XMLHttpRequest();
                        xhr.open("PUT", intent.upload.url);
                        Object.entries(intent.upload.headers).forEach(
                            ([key, values]) => {
                                if (key.toLowerCase() !== "host")
                                    xhr.setRequestHeader(
                                        key,
                                        Array.isArray(values)
                                            ? values.join(", ")
                                            : values,
                                    );
                            },
                        );
                        xhr.upload.onprogress = (e) => {
                            if (e.lengthComputable)
                                status.textContent = `${mediaText("Uploading:")} ${Math.round((e.loaded / e.total) * 100)}%`;
                        };
                        xhr.onload = () =>
                            xhr.status >= 200 && xhr.status < 300
                                ? resolve()
                                : reject(
                                      new Error(mediaText("Upload failed. Please retry.")),
                                  );
                        xhr.onerror = () =>
                            reject(
                                new Error(
                                    mediaText("Upload connection failed. Please retry."),
                                ),
                            );
                        xhr.onabort = () =>
                            reject(
                                new Error(
                                    mediaText("Upload cancelled. You can start a new upload."),
                                ),
                            );
                        xhr.send(file);
                    });
                    status.textContent = mediaText("Verifying video…");
                    await api(
                        `${form.dataset.videoUpload}/${intent.id}/confirm`,
                        {},
                    );
                    await api(
                        `${form.dataset.videoUpload}/${intent.id}/attach`,
                        {
                            lesson_id: Number(
                                form.querySelector('[name="lesson_id"]').value,
                            ),
                            duration_seconds: Number(
                                form.querySelector('[name="duration_seconds"]')
                                    .value,
                            ),
                        },
                    );
                    window.location.reload();
                } catch (error) {
                    status.textContent = error.message;
                } finally {
                    submit.disabled = false;
                    xhr = null;
                }
            });
        });
}
document.addEventListener("DOMContentLoaded", initializeMedia);
document.addEventListener("livewire:navigated", initializeMedia);
let mediaHookRegistered = false;
function registerMediaHook() {
    if (mediaHookRegistered || !window.Livewire) return;
    mediaHookRegistered = true;
    window.Livewire.hook("morph.updated", () =>
        queueMicrotask(initializeMedia),
    );
}
registerMediaHook();
document.addEventListener("livewire:initialized", registerMediaHook);

// Sign-in / sign-up popup: links with data-login-open ("login" or "register") open it on that tab.
// Without JS those links fall back to the /login and /register pages.
function showAuthPanel(dialog, name, focus = false) {
    dialog.querySelectorAll("[data-auth-tab]").forEach((tab) => {
        const active = tab.dataset.authTab === name;
        tab.setAttribute("aria-selected", String(active));
        tab.tabIndex = active ? 0 : -1;
    });
    dialog.querySelectorAll("[data-auth-panel]").forEach((panel) => {
        panel.hidden = panel.dataset.authPanel !== name;
    });
    if (focus) {
        const panel = dialog.querySelector(`[data-auth-panel="${name}"]`);
        (panel.querySelector('[aria-invalid="true"]') || panel.querySelector("input:not([type=hidden])"))?.focus();
    }
}
function openLoginDialog(dialog, name = "login") {
    document.querySelectorAll(".mobile-menu[open]").forEach((menu) => menu.removeAttribute("open"));
    if (!dialog.open) dialog.showModal();
    showAuthPanel(dialog, name, true);
}
// Remember where a press started so a drag out of the card (e.g. selecting text) doesn't close it.
let pressTarget = null;
document.addEventListener("pointerdown", (event) => (pressTarget = event.target), true);
document.addEventListener("click", (event) => {
    const dialog = document.getElementById("login-dialog");
    if (!dialog) return;
    // Clicking the backdrop (outside the card) closes the dialog.
    if (event.target === dialog) {
        if (pressTarget === null || pressTarget === dialog) dialog.close();
        return;
    }
    const tab = event.target.closest("[data-auth-tab]");
    if (tab) return showAuthPanel(dialog, tab.dataset.authTab, true);
    const switcher = event.target.closest("[data-auth-switch]");
    if (switcher && dialog.contains(switcher)) {
        event.preventDefault();
        return showAuthPanel(dialog, switcher.dataset.authSwitch, true);
    }
    const link = event.target.closest("[data-login-open]");
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey) return;
    event.preventDefault();
    openLoginDialog(dialog, link.dataset.loginOpen || "login");
});
// Arrow keys move between the two tabs.
document.addEventListener("keydown", (event) => {
    const tab = event.target.closest?.("[data-auth-tab]");
    if (!tab || !["ArrowLeft", "ArrowRight"].includes(event.key)) return;
    const next = tab.dataset.authTab === "login" ? "register" : "login";
    const dialog = document.getElementById("login-dialog");
    showAuthPanel(dialog, next);
    dialog.querySelector(`[data-auth-tab="${next}"]`).focus();
});
function initializeLoginDialog() {
    const dialog = document.getElementById("login-dialog");
    if (dialog?.dataset.openOnLoad) openLoginDialog(dialog, dialog.dataset.openOnLoad);
}

// Show/hide password buttons.
document.addEventListener("click", (event) => {
    const toggle = event.target.closest("[data-password-toggle]");
    if (!toggle) return;
    const input = document.getElementById(toggle.getAttribute("aria-controls"));
    if (!input) return;
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    toggle.setAttribute("aria-pressed", String(show));
    toggle.setAttribute("aria-label", show ? toggle.dataset.labelHide : toggle.dataset.labelShow);
});

document.addEventListener("DOMContentLoaded", initializeLoginDialog);
document.addEventListener("livewire:navigated", initializeLoginDialog);

// Header search dropdown: focus the box when opened; close on Escape or outside click.
document.addEventListener(
    "toggle",
    (event) => {
        const search = event.target;
        if (search.matches?.("[data-nav-search]") && search.open) search.querySelector("input")?.focus();
    },
    true,
);
// The mobile menu behaves the same way.
const dropdowns = "[data-nav-search][open], .mobile-menu[open]";
document.addEventListener("click", (event) => {
    document.querySelectorAll(dropdowns).forEach((search) => {
        if (!search.contains(event.target)) search.removeAttribute("open");
    });
});
document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    document.querySelectorAll(dropdowns).forEach((search) => {
        search.removeAttribute("open");
        search.querySelector("summary")?.focus();
    });
});

// Signed-in sidebar drawer: the menu button is a label, so give it keyboard support and close on Escape.
document.addEventListener("keydown", (event) => {
    const toggle = document.getElementById("app-nav-toggle");
    if (!toggle) return;
    if (event.target.closest?.(".app-menu-button") && (event.key === "Enter" || event.key === " ")) {
        event.preventDefault();
        toggle.checked = !toggle.checked;
    } else if (event.key === "Escape" && toggle.checked) {
        toggle.checked = false;
        document.querySelector(".app-menu-button")?.focus();
    }
});
