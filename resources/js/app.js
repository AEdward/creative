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
                "Please retry later.",
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
                    status.textContent = "Ready to watch.";
                } catch {
                    status.textContent =
                        "Playback is unavailable. Refresh playback to retry.";
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
                    "Playback stopped or the link expired. Refresh playback to continue from your place.";
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
                        "Your place could not be saved. Check your connection.";
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
                    status.textContent = "Choose an MP4 video up to 1 GB.";
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
                        "Uploading directly to private storage…";
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
                                status.textContent = `Uploading: ${Math.round((e.loaded / e.total) * 100)}%`;
                        };
                        xhr.onload = () =>
                            xhr.status >= 200 && xhr.status < 300
                                ? resolve()
                                : reject(
                                      new Error("Upload failed. Please retry."),
                                  );
                        xhr.onerror = () =>
                            reject(
                                new Error(
                                    "Upload connection failed. Please retry.",
                                ),
                            );
                        xhr.onabort = () =>
                            reject(
                                new Error(
                                    "Upload cancelled. You can start a new upload.",
                                ),
                            );
                        xhr.send(file);
                    });
                    status.textContent = "Verifying video…";
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
