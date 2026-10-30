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

// Login popup: "Log In" links open the dialog; without JS they fall back to /login.
function openLoginDialog(dialog) {
    if (dialog.open) return;
    document.querySelectorAll(".mobile-menu[open]").forEach((menu) => menu.removeAttribute("open"));
    dialog.showModal();
    const field = dialog.querySelector('[aria-invalid="true"]') || dialog.querySelector('input[type="email"]');
    field?.focus();
}
document.addEventListener("click", (event) => {
    const dialog = document.getElementById("login-dialog");
    if (!dialog) return;
    // Clicking the backdrop (outside the card) closes the dialog.
    if (event.target === dialog) return dialog.close();
    const link = event.target.closest("[data-login-open]");
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey) return;
    event.preventDefault();
    openLoginDialog(dialog);
});
function initializeLoginDialog() {
    const dialog = document.getElementById("login-dialog");
    if (dialog?.hasAttribute("data-open-on-load")) openLoginDialog(dialog);
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
