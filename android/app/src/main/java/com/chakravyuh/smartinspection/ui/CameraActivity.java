package com.chakravyuh.smartinspection.ui;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.location.Location;
import android.net.Uri;
import android.os.Bundle;
import android.os.Looper;
import android.provider.Settings;
import android.view.View;
import android.widget.Button;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ImageCapture;
import androidx.camera.core.ImageCaptureException;
import androidx.camera.core.Preview;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.camera.view.PreviewView;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.util.Constants;
import com.chakravyuh.smartinspection.util.WatermarkUtil;
import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationCallback;
import com.google.android.gms.location.LocationRequest;
import com.google.android.gms.location.LocationResult;
import com.google.android.gms.location.LocationServices;
import com.google.android.gms.location.Priority;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.common.util.concurrent.ListenableFuture;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class CameraActivity extends BaseActivity {
    public static final String EXTRA_FILE_PATH = "extra_file_path";
    public static final String EXTRA_LATITUDE = "extra_latitude";
    public static final String EXTRA_LONGITUDE = "extra_longitude";
    public static final String EXTRA_ACCURACY = "extra_accuracy";
    public static final String EXTRA_IS_MOCK = "extra_is_mock";
    public static final String EXTRA_DEVICE_TIME = "extra_device_time";

    private static final int PERMISSION_REQ_CODE = 1001;
    private static final long MIN_STORAGE_REQUIRED_BYTES = 15 * 1024 * 1024; // 15 MB

    private PreviewView previewView;
    private TextView tvGpsStatus;
    private TextView tvGpsAccuracy;
    private View viewGpsDot;
    private TextView tvPreviewTelemetry;
    private FloatingActionButton btnCapturePhoto;
    private Button btnCancelCamera;

    private ImageCapture imageCapture;
    private ExecutorService cameraExecutor;
    private FusedLocationProviderClient fusedLocationClient;
    private LocationCallback locationCallback;
    private Location currentLocation = null;
    private boolean isCapturing = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_camera);

        initViews();
        cameraExecutor = Executors.newSingleThreadExecutor();
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);

        setupEvents();

        if (checkPermissions()) {
            startCamera();
            startLocationUpdates();
        } else {
            requestPermissions();
        }
    }

    private void initViews() {
        previewView = findViewById(R.id.previewView);
        tvGpsStatus = findViewById(R.id.tvGpsStatus);
        tvGpsAccuracy = findViewById(R.id.tvGpsAccuracy);
        viewGpsDot = findViewById(R.id.viewGpsDot);
        tvPreviewTelemetry = findViewById(R.id.tvPreviewTelemetry);
        btnCapturePhoto = findViewById(R.id.btnCapturePhoto);
        btnCancelCamera = findViewById(R.id.btnCancelCamera);
    }

    private void setupEvents() {
        btnCancelCamera.setOnClickListener(v -> finish());
        btnCapturePhoto.setOnClickListener(v -> capturePhotoWithWatermark());
    }

    private boolean checkPermissions() {
        boolean cam = ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED;
        boolean loc = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED;
        return cam && loc;
    }

    private void requestPermissions() {
        ActivityCompat.requestPermissions(this,
            new String[]{Manifest.permission.CAMERA, Manifest.permission.ACCESS_FINE_LOCATION},
            PERMISSION_REQ_CODE);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == PERMISSION_REQ_CODE) {
            boolean allGranted = (grantResults.length >= 2);
            for (int res : grantResults) {
                if (res != PackageManager.PERMISSION_GRANTED) {
                    allGranted = false;
                    break;
                }
            }

            if (allGranted) {
                startCamera();
                startLocationUpdates();
            } else {
                handlePermissionsDenied();
            }
        }
    }

    private void handlePermissionsDenied() {
        boolean showRationale = ActivityCompat.shouldShowRequestPermissionRationale(this, Manifest.permission.CAMERA)
            || ActivityCompat.shouldShowRequestPermissionRationale(this, Manifest.permission.ACCESS_FINE_LOCATION);

        new AlertDialog.Builder(this)
            .setTitle("Camera & Location Required")
            .setMessage(showRationale
                ? "Camera and precise GPS location are mandatory for evidence integrity and geo-tagging under MoSJE guidelines."
                : "Required permissions were permanently denied. Please grant Camera and Location permissions in App Settings to capture evidence.")
            .setPositiveButton(showRationale ? "Grant Permissions" : "Open Settings", (dialog, which) -> {
                if (showRationale) {
                    requestPermissions();
                } else {
                    Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
                    intent.setData(Uri.fromParts("package", getPackageName(), null));
                    startActivity(intent);
                    finish();
                }
            })
            .setNegativeButton("Cancel", (dialog, which) -> finish())
            .setCancelable(false)
            .show();
    }

    private void startCamera() {
        if (isFinishing() || isDestroyed()) return;

        ListenableFuture<ProcessCameraProvider> cameraProviderFuture = ProcessCameraProvider.getInstance(this);
        cameraProviderFuture.addListener(() -> {
            if (isFinishing() || isDestroyed()) return;
            try {
                ProcessCameraProvider cameraProvider = cameraProviderFuture.get();

                Preview preview = new Preview.Builder().build();
                preview.setSurfaceProvider(previewView.getSurfaceProvider());

                int rotation = getWindowManager().getDefaultDisplay().getRotation();

                imageCapture = new ImageCapture.Builder()
                    .setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY)
                    .setTargetRotation(rotation)
                    .build();

                CameraSelector cameraSelector = CameraSelector.DEFAULT_BACK_CAMERA;
                if (!cameraProvider.hasCamera(cameraSelector)) {
                    if (cameraProvider.hasCamera(CameraSelector.DEFAULT_FRONT_CAMERA)) {
                        cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA;
                    }
                }

                cameraProvider.unbindAll();
                cameraProvider.bindToLifecycle(this, cameraSelector, preview, imageCapture);

            } catch (Exception e) {
                showError("Failed to initialize Camera lens: " + e.getMessage());
            }
        }, ContextCompat.getMainExecutor(this));
    }

    private void startLocationUpdates() {
        if (isFinishing() || isDestroyed()) return;

        LocationRequest locationRequest = new LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 1500)
            .setMinUpdateIntervalMillis(1000)
            .build();

        locationCallback = new LocationCallback() {
            @Override
            public void onLocationResult(@NonNull LocationResult locationResult) {
                if (isFinishing() || isDestroyed()) return;
                for (Location location : locationResult.getLocations()) {
                    if (location != null) {
                        onLocationReceived(location);
                    }
                }
            }
        };

        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) {
            fusedLocationClient.requestLocationUpdates(locationRequest, locationCallback, Looper.getMainLooper());
        }
    }

    private void onLocationReceived(Location location) {
        currentLocation = location;
        float accuracy = location.getAccuracy();
        boolean isMock = location.isFromMockProvider();

        tvGpsAccuracy.setText(String.format(Locale.US, "±%.1fm", accuracy));

        // Enforce 100m accuracy limit
        if (accuracy > Constants.MAX_GPS_ACCURACY_METERS) {
            tvGpsStatus.setText("GPS Accuracy Poor (>100m) - Capture Blocked");
            viewGpsDot.setBackgroundColor(ContextCompat.getColor(this, R.color.accent_danger));
            if (!isCapturing) {
                btnCapturePhoto.setEnabled(false);
                btnCapturePhoto.setAlpha(0.4f);
            }
        } else {
            tvGpsStatus.setText(isMock ? "GPS Locked (Mock Detected)" : "GPS Locked (Telemetry Active)");
            viewGpsDot.setBackgroundColor(ContextCompat.getColor(this, isMock ? R.color.accent_warning : R.color.accent_success));
            if (!isCapturing) {
                btnCapturePhoto.setEnabled(true);
                btnCapturePhoto.setAlpha(1.0f);
            }
        }

        SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US);
        tvPreviewTelemetry.setText(String.format(Locale.US, "LAT: %.6f | LNG: %.6f\nTIME: %s",
            location.getLatitude(), location.getLongitude(), sdf.format(new Date())));
    }

    private void capturePhotoWithWatermark() {
        if (isCapturing) return;

        if (currentLocation == null) {
            showError("GPS location lock not yet established. Please wait for coordinates lock.");
            return;
        }

        if (currentLocation.getAccuracy() > Constants.MAX_GPS_ACCURACY_METERS) {
            showError(String.format(Locale.US, "GPS accuracy (±%.1fm) exceeds 100m threshold. Move to an open area.",
                currentLocation.getAccuracy()));
            return;
        }

        if (imageCapture == null) {
            showError("Camera lens is still initializing. Please wait a moment.");
            return;
        }

        File outputDir = new File(getFilesDir(), "evidence");
        if (!outputDir.exists()) outputDir.mkdirs();

        // Check usable storage space
        if (outputDir.getUsableSpace() < MIN_STORAGE_REQUIRED_BYTES) {
            showError("Device storage is critically low (<15MB). Please free space before capturing evidentiary photos.");
            return;
        }

        isCapturing = true;
        btnCapturePhoto.setEnabled(false);
        btnCapturePhoto.setAlpha(0.4f);

        String filename = "INSP_" + System.currentTimeMillis() + ".jpg";
        File rawFile = new File(outputDir, "raw_" + filename);
        File finalFile = new File(outputDir, filename);

        ImageCapture.OutputFileOptions outputOptions = new ImageCapture.OutputFileOptions.Builder(rawFile).build();

        imageCapture.takePicture(outputOptions, cameraExecutor, new ImageCapture.OnImageSavedCallback() {
            @Override
            public void onImageSaved(@NonNull ImageCapture.OutputFileResults outputFileResults) {
                if (isFinishing() || isDestroyed()) {
                    if (rawFile.exists()) rawFile.delete();
                    return;
                }

                try {
                    // Load raw photo into Bitmap
                    Bitmap rawBitmap = BitmapFactory.decodeFile(rawFile.getAbsolutePath());
                    if (rawBitmap == null) {
                        if (rawFile.exists()) rawFile.delete();
                        runOnUiThread(() -> {
                            isCapturing = false;
                            btnCapturePhoto.setEnabled(true);
                            btnCapturePhoto.setAlpha(1.0f);
                            showError("Failed to decode captured frame from camera lens.");
                        });
                        return;
                    }

                    // Burn in pixel-level translucent watermark + telemetry
                    boolean isMock = currentLocation != null && currentLocation.isFromMockProvider();
                    double lat = currentLocation != null ? currentLocation.getLatitude() : 0.0;
                    double lng = currentLocation != null ? currentLocation.getLongitude() : 0.0;
                    float acc = currentLocation != null ? currentLocation.getAccuracy() : 0.0f;

                    Bitmap watermarkedBitmap = WatermarkUtil.applyWatermark(
                        rawBitmap,
                        CameraActivity.this,
                        lat,
                        lng,
                        acc,
                        isMock
                    );

                    // Free raw bitmap memory
                    rawBitmap.recycle();

                    if (watermarkedBitmap == null) {
                        if (rawFile.exists()) rawFile.delete();
                        runOnUiThread(() -> {
                            isCapturing = false;
                            btnCapturePhoto.setEnabled(true);
                            btnCapturePhoto.setAlpha(1.0f);
                            showError("Failed to apply official watermark to image.");
                        });
                        return;
                    }

                    // Compress client-side post-watermarking (JPEG quality 85)
                    FileOutputStream fos = new FileOutputStream(finalFile);
                    watermarkedBitmap.compress(Bitmap.CompressFormat.JPEG, 85, fos);
                    fos.flush();
                    fos.close();

                    // Free watermarked bitmap memory
                    watermarkedBitmap.recycle();

                    // Cleanup raw temp file
                    if (rawFile.exists()) rawFile.delete();

                    // Return result with telemetry
                    runOnUiThread(() -> {
                        isCapturing = false;
                        Intent result = new Intent();
                        result.putExtra(EXTRA_FILE_PATH, finalFile.getAbsolutePath());
                        result.putExtra(EXTRA_LATITUDE, lat);
                        result.putExtra(EXTRA_LONGITUDE, lng);
                        result.putExtra(EXTRA_ACCURACY, acc);
                        result.putExtra(EXTRA_IS_MOCK, isMock);
                        result.putExtra(EXTRA_DEVICE_TIME, System.currentTimeMillis());

                        setResult(RESULT_OK, result);
                        finish();
                    });

                } catch (IOException e) {
                    if (rawFile.exists()) rawFile.delete();
                    runOnUiThread(() -> {
                        isCapturing = false;
                        btnCapturePhoto.setEnabled(true);
                        btnCapturePhoto.setAlpha(1.0f);
                        showError("Failed to save watermarked evidence: " + e.getMessage());
                    });
                }
            }

            @Override
            public void onError(@NonNull ImageCaptureException exception) {
                runOnUiThread(() -> {
                    isCapturing = false;
                    btnCapturePhoto.setEnabled(true);
                    btnCapturePhoto.setAlpha(1.0f);
                    showError("Capture failed: " + exception.getMessage());
                });
            }
        });
    }

    @Override
    protected void onPause() {
        super.onPause();
        if (fusedLocationClient != null && locationCallback != null) {
            fusedLocationClient.removeLocationUpdates(locationCallback);
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (checkPermissions()) {
            startLocationUpdates();
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (fusedLocationClient != null && locationCallback != null) {
            fusedLocationClient.removeLocationUpdates(locationCallback);
        }
        if (cameraExecutor != null) {
            cameraExecutor.shutdown();
        }
    }
}
