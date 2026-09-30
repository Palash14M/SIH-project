package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.TextView;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AlertDialog;
import androidx.work.Constraints;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.db.AppDatabase;
import com.chakravyuh.smartinspection.db.entity.EvidenceEntity;
import com.chakravyuh.smartinspection.db.entity.InspectionEntity;
import com.chakravyuh.smartinspection.network.ApiClient;
import com.chakravyuh.smartinspection.util.SyncWorker;
import com.google.gson.Gson;
import java.io.File;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.Executors;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class InspectionFormActivity extends BaseActivity {
    public static final String EXTRA_TENDER_ID = "extra_tender_id";

    private TextView tvFormTenderTitle;
    private TextView tvFormTenderNumber;
    private TextView tvLiveProgress;
    private TextView tvLiveSpent;
    private TextView tvLiveVariance;
    private TextView tvVarianceWarning;

    private LinearLayout layoutMilestonesContainer;
    private EditText etActualSpent;
    private EditText etSpentBasis;
    private Button btnCaptureVoucher;
    private TextView tvVoucherProofStatus;

    private LinearLayout layoutQualityChecklistContainer;
    private EditText etRemarks;
    private Button btnSubmitInspection;

    private int tenderId = 1;
    private double sanctionedAmount = 45000000.0;
    private String voucherPhotoPath = null;
    private double voucherLat = 0.0, voucherLng = 0.0;
    private float voucherAccuracy = 0.0f;
    private boolean voucherIsMock = false;

    private final List<MilestoneInput> milestoneInputs = new ArrayList<>();
    private final List<ChecklistInput> checklistInputs = new ArrayList<>();

    private static class MilestoneInput {
        int id;
        String name;
        double weight;
        EditText etProgress;
    }

    private static class ChecklistInput {
        int id;
        String item;
        boolean isCritical;
        RadioGroup rgStatus;
        EditText etItemRemarks;
        String evidencePath;
    }

    private ActivityResultLauncher<Intent> voucherCameraLauncher;
    private ActivityResultLauncher<Intent> checklistCameraLauncher;
    private ChecklistInput activeChecklistCapture = null;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_inspection_form);

        if (getIntent().hasExtra(EXTRA_TENDER_ID)) {
            tenderId = getIntent().getIntExtra(EXTRA_TENDER_ID, 1);
        }

        setupLaunchers();
        initViews();
        setupCalculations();
        loadTenderData();
    }

    private void setupLaunchers() {
        voucherCameraLauncher = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(),
            result -> {
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    Intent data = result.getData();
                    voucherPhotoPath = data.getStringExtra(CameraActivity.EXTRA_FILE_PATH);
                    voucherLat = data.getDoubleExtra(CameraActivity.EXTRA_LATITUDE, 0.0);
                    voucherLng = data.getDoubleExtra(CameraActivity.EXTRA_LONGITUDE, 0.0);
                    voucherAccuracy = data.getFloatExtra(CameraActivity.EXTRA_ACCURACY, 0.0f);
                    voucherIsMock = data.getBooleanExtra(CameraActivity.EXTRA_IS_MOCK, false);

                    tvVoucherProofStatus.setText(String.format(Locale.US,
                        "✓ Voucher photo attached (LAT: %.4f, ±%.1fm)", voucherLat, voucherAccuracy));
                    tvVoucherProofStatus.setTextColor(getColor(R.color.accent_success));
                }
            }
        );

        checklistCameraLauncher = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(),
            result -> {
                if (result.getResultCode() == RESULT_OK && result.getData() != null && activeChecklistCapture != null) {
                    Intent data = result.getData();
                    activeChecklistCapture.evidencePath = data.getStringExtra(CameraActivity.EXTRA_FILE_PATH);
                    showToast("Photo evidence attached to checklist item.");
                }
            }
        );
    }

    private void initViews() {
        tvFormTenderTitle = findViewById(R.id.tvFormTenderTitle);
        tvFormTenderNumber = findViewById(R.id.tvFormTenderNumber);
        tvLiveProgress = findViewById(R.id.tvLiveProgress);
        tvLiveSpent = findViewById(R.id.tvLiveSpent);
        tvLiveVariance = findViewById(R.id.tvLiveVariance);
        tvVarianceWarning = findViewById(R.id.tvVarianceWarning);

        layoutMilestonesContainer = findViewById(R.id.layoutMilestonesContainer);
        etActualSpent = findViewById(R.id.etActualSpent);
        etSpentBasis = findViewById(R.id.etSpentBasis);
        btnCaptureVoucher = findViewById(R.id.btnCaptureVoucher);
        tvVoucherProofStatus = findViewById(R.id.tvVoucherProofStatus);

        layoutQualityChecklistContainer = findViewById(R.id.layoutQualityChecklistContainer);
        etRemarks = findViewById(R.id.etRemarks);
        btnSubmitInspection = findViewById(R.id.btnSubmitInspection);

        btnCaptureVoucher.setOnClickListener(v -> {
            Intent intent = new Intent(this, CameraActivity.class);
            voucherCameraLauncher.launch(intent);
        });

        btnSubmitInspection.setOnClickListener(v -> submitInspectionOffline());
    }

    private void setupCalculations() {
        etActualSpent.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            @Override public void onTextChanged(CharSequence s, int start, int before, int count) {
                recomputeTelemetry();
            }
            @Override public void afterTextChanged(Editable s) {}
        });
    }

    private void loadTenderData() {
        ApiClient.getApiService().getTenderDetail(tenderId).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    Map<String, Object> data = (Map<String, Object>) body.get("data");
                    if (data != null) {
                        renderTenderDetails(data);
                    }
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                // Populate default demonstration data for offline resilience
                populateDemoData();
            }
        });
    }

    private void renderTenderDetails(Map<String, Object> data) {
        String num = (String) data.get("tender_number");
        String title = (String) data.get("title");
        Number amt = (Number) data.get("sanctioned_amount");
        if (amt != null) sanctionedAmount = amt.doubleValue();

        tvFormTenderNumber.setText(num);
        tvFormTenderTitle.setText(title);

        List<Map<String, Object>> milestones = (List<Map<String, Object>>) data.get("milestones");
        renderMilestones(milestones);

        List<Map<String, Object>> checklist = (List<Map<String, Object>>) data.get("quality_checklist");
        renderChecklist(checklist);
    }

    private void populateDemoData() {
        tvFormTenderNumber.setText("TND-MH-NGP-2026-001");
        tvFormTenderTitle.setText("Ring Road Phase-3 Strengthening");

        List<Map<String, Object>> mList = new ArrayList<>();
        Map<String, Object> m1 = new HashMap<>(); m1.put("id", 1); m1.put("milestone_name", "Subgrade Preparation"); m1.put("planned_weight_pct", 25.0); mList.add(m1);
        Map<String, Object> m2 = new HashMap<>(); m2.put("id", 2); m2.put("milestone_name", "Dense Bituminous Macadam (DBM)"); m2.put("planned_weight_pct", 35.0); mList.add(m2);
        Map<String, Object> m3 = new HashMap<>(); m3.put("id", 3); m3.put("milestone_name", "Bituminous Concrete (Wearing Coat)"); m3.put("planned_weight_pct", 40.0); mList.add(m3);
        renderMilestones(mList);

        List<Map<String, Object>> qList = new ArrayList<>();
        Map<String, Object> q1 = new HashMap<>(); q1.put("id", 1); q1.put("item_text", "Compaction density >= 98% Proctor"); q1.put("is_critical", 1); qList.add(q1);
        Map<String, Object> q2 = new HashMap<>(); q2.put("id", 2); q2.put("item_text", "Bitumen penetration grade certificate"); q2.put("is_critical", 0); qList.add(q2);
        renderChecklist(qList);
    }

    private void renderMilestones(List<Map<String, Object>> milestones) {
        layoutMilestonesContainer.removeAllViews();
        milestoneInputs.clear();

        if (milestones == null || milestones.isEmpty()) return;

        for (Map<String, Object> m : milestones) {
            Number idNum = (Number) m.get("id");
            String name = (String) m.get("milestone_name");
            Number weightNum = (Number) m.get("planned_weight_pct");
            final double weight = weightNum != null ? weightNum.doubleValue() : 0.0;

            LinearLayout row = new LinearLayout(this);
            row.setOrientation(LinearLayout.VERTICAL);
            row.setPadding(0, 8, 0, 12);

            TextView label = new TextView(this);
            label.setText(String.format(Locale.US, "%s (Weight: %.0f%%)", name, weight));
            label.setTextColor(getColor(R.color.text_primary));
            label.setTextSize(13);

            EditText et = new EditText(this);
            et.setHint("Actual completion % (0-100)");
            et.setInputType(android.text.InputType.TYPE_CLASS_NUMBER | android.text.InputType.TYPE_NUMBER_FLAG_DECIMAL);
            et.setTextSize(14);
            et.addTextChangedListener(new TextWatcher() {
                @Override public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
                @Override public void onTextChanged(CharSequence s, int start, int before, int count) { recomputeTelemetry(); }
                @Override public void afterTextChanged(Editable s) {}
            });

            row.addView(label);
            row.addView(et);
            layoutMilestonesContainer.addView(row);

            MilestoneInput mi = new MilestoneInput();
            mi.id = idNum != null ? idNum.intValue() : 0;
            mi.name = name;
            mi.weight = weight;
            mi.etProgress = et;
            milestoneInputs.add(mi);
        }
    }

    private void renderChecklist(List<Map<String, Object>> checklist) {
        layoutQualityChecklistContainer.removeAllViews();
        checklistInputs.clear();

        if (checklist == null || checklist.isEmpty()) return;

        for (Map<String, Object> q : checklist) {
            Number idNum = (Number) q.get("id");
            String item = (String) q.get("item_text");
            Number critNum = (Number) q.get("is_critical");
            boolean isCritical = critNum != null && critNum.intValue() == 1;

            LinearLayout itemLayout = new LinearLayout(this);
            itemLayout.setOrientation(LinearLayout.VERTICAL);
            itemLayout.setPadding(0, 8, 0, 14);

            TextView tvItem = new TextView(this);
            tvItem.setText(String.format("%s%s", item, isCritical ? " [CRITICAL]" : ""));
            tvItem.setTextColor(getColor(isCritical ? R.color.accent_danger : R.color.text_primary));
            tvItem.setTextSize(13);
            tvItem.setTypeface(null, isCritical ? android.graphics.Typeface.BOLD : android.graphics.Typeface.NORMAL);

            RadioGroup rg = new RadioGroup(this);
            rg.setOrientation(RadioGroup.HORIZONTAL);
            RadioButton rbPass = new RadioButton(this); rbPass.setText("Pass"); rbPass.setId(View.generateViewId());
            RadioButton rbFail = new RadioButton(this); rbFail.setText("Fail"); rbFail.setId(View.generateViewId());
            RadioButton rbNa = new RadioButton(this); rbNa.setText("N/A"); rbNa.setId(View.generateViewId());
            rbPass.setChecked(true);
            rg.addView(rbPass);
            rg.addView(rbFail);
            rg.addView(rbNa);

            EditText etRemarksItem = new EditText(this);
            etRemarksItem.setHint("Check remarks / observations...");
            etRemarksItem.setTextSize(12);

            Button btnCam = new Button(this);
            btnCam.setText("📷 Evidence Photo");
            btnCam.setTextSize(11);
            btnCam.setBackgroundColor(getColor(R.color.background));

            ChecklistInput ci = new ChecklistInput();
            ci.id = idNum != null ? idNum.intValue() : 0;
            ci.item = item;
            ci.isCritical = isCritical;
            ci.rgStatus = rg;
            ci.etItemRemarks = etRemarksItem;
            checklistInputs.add(ci);

            btnCam.setOnClickListener(v -> {
                activeChecklistCapture = ci;
                Intent intent = new Intent(this, CameraActivity.class);
                checklistCameraLauncher.launch(intent);
            });

            itemLayout.addView(tvItem);
            itemLayout.addView(rg);
            itemLayout.addView(etRemarksItem);
            itemLayout.addView(btnCam);
            layoutQualityChecklistContainer.addView(itemLayout);
        }
    }

    private void recomputeTelemetry() {
        double overallProgress = 0.0;
        for (MilestoneInput mi : milestoneInputs) {
            String valStr = mi.etProgress.getText().toString().trim();
            if (!valStr.isEmpty()) {
                try {
                    double val = Double.parseDouble(valStr);
                    overallProgress += (val * (mi.weight / 100.0));
                } catch (NumberFormatException ignored) {}
            }
        }

        double actualSpent = 0.0;
        String spentStr = etActualSpent.getText().toString().trim();
        if (!spentStr.isEmpty()) {
            try {
                actualSpent = Double.parseDouble(spentStr);
            } catch (NumberFormatException ignored) {}
        }

        double spentPct = sanctionedAmount > 0 ? (actualSpent / sanctionedAmount) * 100.0 : 0.0;
        double variance = spentPct - overallProgress;

        tvLiveProgress.setText(String.format(Locale.US, "%.1f%%", overallProgress));
        tvLiveSpent.setText(String.format(Locale.US, "%.1f%%", spentPct));
        tvLiveVariance.setText(String.format(Locale.US, "%+.1f%%", variance));

        if (variance > 10.0) {
            tvLiveVariance.setTextColor(getColor(R.color.accent_danger));
            tvVarianceWarning.setVisibility(View.VISIBLE);
        } else {
            tvLiveVariance.setTextColor(getColor(R.color.accent_success));
            tvVarianceWarning.setVisibility(View.GONE);
        }
    }

    private void submitInspectionOffline() {
        String remarks = etRemarks.getText().toString().trim();
        String spentStr = etActualSpent.getText().toString().trim();
        String basis = etSpentBasis.getText().toString().trim();

        if (remarks.isEmpty() || spentStr.isEmpty() || basis.isEmpty()) {
            showError("Please fill all required inspection fields (Spent, Basis, and Remarks).");
            return;
        }

        double actualSpent = Double.parseDouble(spentStr);
        double overallProgress = 0.0;
        List<Map<String, Object>> mProgressList = new ArrayList<>();
        for (MilestoneInput mi : milestoneInputs) {
            double p = 0.0;
            String v = mi.etProgress.getText().toString().trim();
            if (!v.isEmpty()) p = Double.parseDouble(v);
            overallProgress += (p * (mi.weight / 100.0));

            Map<String, Object> map = new HashMap<>();
            map.put("milestone_id", mi.id);
            map.put("actual_progress_pct", p);
            mProgressList.add(map);
        }

        boolean hasCriticalFailure = false;
        List<Map<String, Object>> qCheckList = new ArrayList<>();
        for (ChecklistInput ci : checklistInputs) {
            int selectedId = ci.rgStatus.getCheckedRadioButtonId();
            RadioButton rb = findViewById(selectedId);
            String status = rb != null ? rb.getText().toString().toUpperCase() : "PASS";

            if ("FAIL".equalsIgnoreCase(status) && ci.isCritical) {
                hasCriticalFailure = true;
            }

            Map<String, Object> map = new HashMap<>();
            map.put("template_id", ci.id);
            map.put("status", status);
            map.put("remarks", ci.etItemRemarks.getText().toString().trim());
            map.put("evidence_path", ci.evidencePath);
            qCheckList.add(map);
        }

        Gson gson = new Gson();
        InspectionEntity entity = new InspectionEntity();
        entity.tenderId = tenderId;
        entity.tenderNumber = tvFormTenderNumber.getText().toString();
        entity.tenderTitle = tvFormTenderTitle.getText().toString();
        entity.overallProgress = overallProgress;
        entity.actualSpent = actualSpent;
        entity.spentBasis = basis;
        entity.spentDocLocalPath = voucherPhotoPath;
        entity.remarks = remarks;
        entity.issueFound = hasCriticalFailure;
        entity.severity = hasCriticalFailure ? "CRITICAL" : "LOW";
        entity.qualityChecksJson = gson.toJson(qCheckList);
        entity.milestoneProgressJson = gson.toJson(mProgressList);
        entity.syncStatus = "PENDING";
        entity.createdAt = System.currentTimeMillis();

        btnSubmitInspection.setEnabled(false);
        btnSubmitInspection.setText("Saving locally...");

        final double finalOverallProgress = overallProgress;
        final boolean finalHasCriticalFailure = hasCriticalFailure;

        Executors.newSingleThreadExecutor().execute(() -> {
            AppDatabase db = AppDatabase.getInstance(this);
            long localInspId = db.inspectionDao().insertInspection(entity);

            if (voucherPhotoPath != null) {
                EvidenceEntity ee = new EvidenceEntity();
                ee.localInspectionId = localInspId;
                ee.localFilePath = voucherPhotoPath;
                ee.mediaType = "PHOTO";
                ee.latitude = voucherLat;
                ee.longitude = voucherLng;
                ee.accuracy = voucherAccuracy;
                ee.isMock = voucherIsMock;
                ee.deviceCaptureTime = System.currentTimeMillis();
                ee.syncStatus = "PENDING";
                db.inspectionDao().insertEvidence(ee);
            }

            // Enqueue WorkManager for auto-sync on connectivity
            Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

            OneTimeWorkRequest syncRequest = new OneTimeWorkRequest.Builder(SyncWorker.class)
                .setConstraints(constraints)
                .build();

            WorkManager.getInstance(this).enqueue(syncRequest);

            runOnUiThread(() -> {
                new AlertDialog.Builder(this)
                    .setTitle("Inspection Saved Offline")
                    .setMessage(String.format(Locale.US,
                        "Saved to local database (Local ID: #%d). Overall Progress: %.1f%%, Issue Flag: %s. Background sync scheduled via WorkManager.",
                        localInspId, finalOverallProgress, finalHasCriticalFailure ? "YES (Critical Failure)" : "NO"))
                    .setPositiveButton("Done", (d, w) -> finish())
                    .setCancelable(false)
                    .show();
            });
        });
    }
}
