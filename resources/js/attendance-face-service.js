const initializeFaceServicePage = (page) => {
    if (page.dataset.faceServiceReady === 'ready') {
        return;
    }

    page.dataset.faceServiceReady = 'ready';

    const video = page.querySelector('[data-attendance-video]');
    const canvas = page.querySelector('[data-attendance-canvas]');
    const cameraButton = page.querySelector('[data-attendance-camera]');
    const message = page.querySelector('[data-attendance-message]');
    const messageText = message?.querySelector('span');
    const progressBar = page.querySelector('[data-attendance-progress]');
    const enrollForm = page.querySelector('[data-face-enrollment-form]');
    const enrollButton = page.querySelector('[data-face-enroll]');
    const enrollmentImagesInput = page.querySelector('[data-face-enrollment-images]');
    const enrollmentSampleInput = page.querySelector('[data-face-enrollment-sample-count]');
    const enrollmentQualityInput = page.querySelector('[data-face-enrollment-quality]');
    const attendanceForm = page.querySelector('[data-attendance-form]');
    const attendanceButton = page.querySelector('[data-attendance-submit]');
    const capturedImageInput = page.querySelector('[data-attendance-captured-image]');
    const latitudeInput = page.querySelector('[data-attendance-latitude]');
    const longitudeInput = page.querySelector('[data-attendance-longitude]');
    const accuracyInput = page.querySelector('[data-attendance-accuracy]');
    const deviceInput = page.querySelector('[data-attendance-device]');
    const detectionUrl = page.dataset.faceDetectionUrl || '';
    const csrfToken = page.querySelector('input[name="_token"]')?.value
        || document.querySelector('input[name="_token"]')?.value
        || '';
    let stream = null;

    const wait = (duration) => new Promise((resolve) => {
        window.setTimeout(resolve, duration);
    });

    const setMessage = (text, isError = false) => {
        if (messageText) {
            messageText.textContent = text;
        }

        message?.classList.toggle('is-error', isError);
    };

    const setProgress = (value) => {
        if (!progressBar) {
            return;
        }

        const safeValue = Math.max(0, Math.min(1, value));

        progressBar.style.width = `${safeValue * 100}%`;
    };

    const setBusy = (button, busy, busyText = 'Memproses...') => {
        if (!button) {
            return;
        }

        const label = button.querySelector('span');

        if (!button.dataset.originalText && label) {
            button.dataset.originalText = label.textContent;
        }

        button.disabled = busy;

        if (label) {
            label.textContent = busy ? busyText : button.dataset.originalText;
        }
    };

    const ensureVideoReady = () => new Promise((resolve) => {
        if (video.readyState >= 2 && video.videoWidth > 0) {
            resolve();
            return;
        }

        video.addEventListener('loadedmetadata', resolve, { once: true });
    });

    const startCamera = async () => {
        if (!video || !canvas) {
            throw new Error('Komponen kamera belum tersedia.');
        }

        if (stream) {
            await ensureVideoReady();
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Kamera tidak tersedia di browser ini.');
        }

        stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: 'user',
                width: { ideal: 960 },
                height: { ideal: 720 },
            },
        });

        video.srcObject = stream;
        await video.play();
        await ensureVideoReady();
        setMessage('Kamera aktif. Posisikan wajah di area oval.');
    };

    const captureImage = () => {
        const context = canvas.getContext('2d');
        const sourceWidth = video.videoWidth;
        const sourceHeight = video.videoHeight;
        const maxWidth = 720;
        const scale = sourceWidth > maxWidth ? maxWidth / sourceWidth : 1;

        canvas.width = Math.max(1, Math.round(sourceWidth * scale));
        canvas.height = Math.max(1, Math.round(sourceHeight * scale));
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/jpeg', 0.86);
    };

    const detectFace = async () => {
        if (!detectionUrl) {
            throw new Error('Endpoint deteksi wajah belum tersedia.');
        }

        await startCamera();
        await wait(180);
        setMessage('Mendeteksi wajah di kamera...');

        const image = captureImage();
        const response = await fetch(detectionUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                txtFaceDetectionImage: image,
            }),
        });

        let payload = {};

        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok || payload.detected === false) {
            throw new Error(payload.message || 'Wajah tidak terdeteksi. Posisikan wajah di area kamera.');
        }

        setMessage('Wajah terdeteksi. Melanjutkan proses.');

        return image;
    };

    const captureImages = async (sampleCount) => {
        await startCamera();
        setProgress(0);

        const images = [];

        for (let index = 0; index < sampleCount; index += 1) {
            await wait(index === 0 ? 180 : 360);
            images.push(captureImage());
            setProgress((index + 1) / sampleCount);
            setMessage(`Mengambil sampel wajah ${index + 1}/${sampleCount}`);
        }

        return images;
    };

    const locationErrorMessage = (error, permissionState = '') => {
        const suffix = permissionState ? ` Status browser: ${permissionState}.` : '';

        if (error?.code === 1) {
            return `Lokasi ditolak oleh browser atau sistem operasi.${suffix} Pastikan site permission dan Location Services perangkat aktif.`;
        }

        if (error?.code === 2) {
            return `Lokasi belum bisa ditemukan.${suffix} Aktifkan GPS/Wi-Fi lalu coba lagi.`;
        }

        if (error?.code === 3) {
            return `Pengambilan lokasi timeout.${suffix} Coba ulang di area dengan sinyal lokasi lebih stabil.`;
        }

        return error?.message || 'Lokasi belum bisa dibaca.';
    };

    const getLocationPermissionState = async () => {
        if (!navigator.permissions?.query) {
            return '';
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });

            return result.state || '';
        } catch (error) {
            return '';
        }
    };

    const getLocation = async () => {
        if (!navigator.geolocation) {
            throw new Error('Geolocation tidak tersedia di browser ini.');
        }

        const permissionState = await getLocationPermissionState();

        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, (error) => {
                error.readableMessage = locationErrorMessage(error, permissionState);
                reject(error);
            }, {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
            });
        });
    };

    cameraButton?.addEventListener('click', async () => {
        setBusy(cameraButton, true, '');

        try {
            await startCamera();
        } catch (error) {
            setMessage(error.message, true);
        } finally {
            setBusy(cameraButton, false);
        }
    });

    enrollButton?.addEventListener('click', async () => {
        setBusy(enrollButton, true, 'Memindai...');

        try {
            if (!enrollForm || !enrollmentImagesInput || !enrollmentSampleInput || !enrollmentQualityInput) {
                throw new Error('Form Face ID belum lengkap.');
            }

            await detectFace();

            const sampleCount = 3;
            const images = await captureImages(sampleCount);

            enrollmentImagesInput.value = JSON.stringify(images);
            enrollmentSampleInput.value = String(sampleCount);
            enrollmentQualityInput.value = '1';
            setMessage('Sampel wajah dikirim ke face service.');
            enrollForm.requestSubmit();
        } catch (error) {
            setMessage(error.message, true);
            setProgress(0);
            setBusy(enrollButton, false);
        }
    });

    attendanceButton?.addEventListener('click', async () => {
        if (attendanceButton.disabled) {
            setMessage(attendanceButton.dataset.disabledReason || 'Absensi belum bisa dilakukan.', true);
            return;
        }

        setBusy(attendanceButton, true, 'Memproses...');

        try {
            if (!attendanceForm || !capturedImageInput || !latitudeInput || !longitudeInput || !accuracyInput || !deviceInput) {
                throw new Error('Form absensi belum lengkap.');
            }

            await detectFace();
            setMessage('Meminta lokasi...');

            const position = await getLocation();

            latitudeInput.value = position.coords.latitude;
            longitudeInput.value = position.coords.longitude;
            accuracyInput.value = position.coords.accuracy || '';
            deviceInput.value = `${navigator.platform || 'Device'} | ${navigator.userAgent || 'Browser'}`.slice(0, 500);
            setMessage('Mengambil foto wajah...');

            const images = await captureImages(1);

            capturedImageInput.value = images[0];
            setMessage('Foto dan lokasi dikirim untuk verifikasi.');
            attendanceForm.requestSubmit();
        } catch (error) {
            setMessage(error.readableMessage || error.message, true);
            setProgress(0);
            setBusy(attendanceButton, false);
        }
    });
};

const initializeAttendanceFaceService = () => {
    document
        .querySelectorAll('[data-attendance-page][data-attendance-mode="python"]')
        .forEach(initializeFaceServicePage);
};

document.addEventListener('DOMContentLoaded', initializeAttendanceFaceService);
