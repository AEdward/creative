export const mediaText = (message) => {
    const element = document.getElementById("media-localization");
    const messages = element ? JSON.parse(element.dataset.messages) : {};
    return messages[message] ?? message;
};
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
export async function api(url, body) {
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

/** Read the length of a local video file so nobody has to type it in seconds. */
function videoDuration(file) {
    return new Promise((resolve) => {
        const probe = document.createElement("video");
        probe.preload = "metadata";
        probe.onloadedmetadata = () => {
            URL.revokeObjectURL(probe.src);
            resolve(Number.isFinite(probe.duration) ? Math.ceil(probe.duration) : 0);
        };
        probe.onerror = () => resolve(0);
        probe.src = URL.createObjectURL(file);
    });
}

// Private video uploader (course builder and Admin -> Media Library): direct browser upload to R2,
// then verify and attach to the chosen lesson.
export function initializeUploads() {
    document.querySelectorAll("[data-video-upload] input[type=file]:not([data-duration-bound])").forEach((input) => {
        input.dataset.durationBound = "true";
        input.addEventListener("change", async () => {
            const field = input.form?.querySelector('[name="duration_seconds"]');
            const file = input.files[0];
            if (field && file) {
                const seconds = await videoDuration(file);
                if (seconds > 0) field.value = seconds;
            }
        });
    });
    document
        .querySelectorAll("[data-video-upload]:not([data-initialized])")
        .forEach((form) => {
            form.dataset.initialized = "true";
            const status = form.querySelector('[role="status"]');
            const submit = form.querySelector('[type="submit"]');
            const bar = form.querySelector("[data-upload-progress]");
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
                if (bar) {
                    bar.value = 0;
                    bar.hidden = false;
                }
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
                            if (e.lengthComputable && bar)
                                bar.value = Math.round((e.loaded / e.total) * 100);
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
                    if (bar) bar.hidden = true;
                    submit.textContent = mediaText("Retry upload");
                } finally {
                    submit.disabled = false;
                    xhr = null;
                }
            });
        });
}

document.addEventListener("DOMContentLoaded", initializeUploads);
document.addEventListener("livewire:navigated", initializeUploads);
document.addEventListener("livewire:initialized", () =>
    window.Livewire?.hook("morph.updated", () => queueMicrotask(initializeUploads)),
);
