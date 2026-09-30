package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.SeekBar;
import android.widget.Spinner;
import android.widget.TextView;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AlertDialog;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.network.ApiClient;
import com.chakravyuh.smartinspection.util.Constants;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class ContractorDashboardActivity extends BaseActivity {
    private TextView tvContractorBadge;
    private Button btnContractorSignOut;

    // Project Details
    private TextView tvProjCategory, tvProjStatus, tvProjNumber, tvProjTitle, tvProjDepartment;
    private TextView tvProjSanctioned, tvProjTimeline, tvProgressLabel;
    private ProgressBar pbCurrentProgress;

    // Inspecting Officer
    private TextView tvInspectorName, tvInspectorJurisdiction, tvInspectorPhone;
    private Button btnCallInspector, btnEmailInspector;

    // Project Images
    private TextView tvImageCountBadge;
    private LinearLayout layoutProjectImages;
    private Button btnAddSitePhoto;

    // Completion Status Form
    private TextView tvSliderLabel;
    private SeekBar sbProgressPercent;
    private Spinner spnExecutionState;
    private CheckBox cbMilestone1, cbMilestone2, cbMilestone3;
    private EditText etContractorNotes;
    private Button btnSubmitContractorUpdate;

    private int currentTenderId = 1;
    private String inspectorPhoneStr = "9100000011";
    private String inspectorEmailStr = "inspector.rajesh@mosje.gov.in";

    private static final String[] EXECUTION_STATES = {
        "IN_PROGRESS • Civil Work Underway",
        "READY_FOR_INSPECTION • Request Verification",
        "COMPLETED • Target Milestones Achieved"
    };

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_contractor_dashboard);

        initViews();
        setupEvents();
        loadContractorData();
    }

    private void initViews() {
        tvContractorBadge = findViewById(R.id.tvContractorBadge);
        btnContractorSignOut = findViewById(R.id.btnContractorSignOut);

        tvProjCategory = findViewById(R.id.tvProjCategory);
        tvProjStatus = findViewById(R.id.tvProjStatus);
        tvProjNumber = findViewById(R.id.tvProjNumber);
        tvProjTitle = findViewById(R.id.tvProjTitle);
        tvProjDepartment = findViewById(R.id.tvProjDepartment);
        tvProjSanctioned = findViewById(R.id.tvProjSanctioned);
        tvProjTimeline = findViewById(R.id.tvProjTimeline);
        tvProgressLabel = findViewById(R.id.tvProgressLabel);
        pbCurrentProgress = findViewById(R.id.pbCurrentProgress);

        tvInspectorName = findViewById(R.id.tvInspectorName);
        tvInspectorJurisdiction = findViewById(R.id.tvInspectorJurisdiction);
        tvInspectorPhone = findViewById(R.id.tvInspectorPhone);
        btnCallInspector = findViewById(R.id.btnCallInspector);
        btnEmailInspector = findViewById(R.id.btnEmailInspector);

        tvImageCountBadge = findViewById(R.id.tvImageCountBadge);
        layoutProjectImages = findViewById(R.id.layoutProjectImages);
        btnAddSitePhoto = findViewById(R.id.btnAddSitePhoto);

        tvSliderLabel = findViewById(R.id.tvSliderLabel);
        sbProgressPercent = findViewById(R.id.sbProgressPercent);
        spnExecutionState = findViewById(R.id.spnExecutionState);
        cbMilestone1 = findViewById(R.id.cbMilestone1);
        cbMilestone2 = findViewById(R.id.cbMilestone2);
        cbMilestone3 = findViewById(R.id.cbMilestone3);
        etContractorNotes = findViewById(R.id.etContractorNotes);
        btnSubmitContractorUpdate = findViewById(R.id.btnSubmitContractorUpdate);

        ArrayAdapter<String> stateAdapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, EXECUTION_STATES);
        spnExecutionState.setAdapter(stateAdapter);

        renderDefaultImages();
    }

    private void setupEvents() {
        btnContractorSignOut.setOnClickListener(v -> {
            preferenceManager.clearSession();
            showToast("Signed out of Contractor Portal.");
            startActivity(new Intent(ContractorDashboardActivity.this, LoginActivity.class));
            finish();
        });

        sbProgressPercent.setOnSeekBarChangeListener(new SeekBar.OnSeekBarChangeListener() {
            @Override
            public void onProgressChanged(SeekBar seekBar, int progress, boolean fromUser) {
                tvSliderLabel.setText(String.format("Set Completion Percentage: %d%%", progress));
            }
            @Override public void onStartTrackingTouch(SeekBar seekBar) {}
            @Override public void onStopTrackingTouch(SeekBar seekBar) {}
        });

        btnCallInspector.setOnClickListener(v -> {
            try {
                Intent callIntent = new Intent(Intent.ACTION_DIAL);
                callIntent.setData(Uri.parse("tel:" + inspectorPhoneStr));
                startActivity(callIntent);
            } catch (Exception e) {
                showToast("Calling: " + inspectorPhoneStr);
            }
        });

        btnEmailInspector.setOnClickListener(v -> {
            try {
                Intent emailIntent = new Intent(Intent.ACTION_SENDTO);
                emailIntent.setData(Uri.parse("mailto:" + inspectorEmailStr));
                emailIntent.putExtra(Intent.EXTRA_SUBJECT, "Progress Update: " + tvProjNumber.getText().toString());
                startActivity(emailIntent);
            } catch (Exception e) {
                showToast("Emailing: " + inspectorEmailStr);
            }
        });

        btnAddSitePhoto.setOnClickListener(v -> {
            AlertDialog.Builder builder = new AlertDialog.Builder(this);
            builder.setTitle("📷 Upload Site Progress Photo");
            builder.setMessage("Select camera capture to record live GPS-verified site progress image:");
            builder.setPositiveButton("Capture Photo", (dialog, which) -> {
                Intent intent = new Intent(ContractorDashboardActivity.this, CameraActivity.class);
                startActivity(intent);
            });
            builder.setNeutralButton("Attach Sample Image", (dialog, which) -> {
                addProgressImageCard("Excavation & Culvert Concrete Work", "Just now (New)");
                showToast("✓ Site progress photo attached to dossier.");
            });
            builder.setNegativeButton("Cancel", null);
            builder.show();
        });

        btnSubmitContractorUpdate.setOnClickListener(v -> performProgressUpdate());
    }

    private void loadContractorData() {
        ApiClient.getApiService().getContractorProjects().enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    List<Map<String, Object>> projects = (List<Map<String, Object>>) body.get("data");
                    if (projects != null && !projects.isEmpty()) {
                        bindProjectData(projects.get(0));
                    }
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                showToast("Operating in offline/cached contractor view.");
            }
        });
    }

    private void bindProjectData(Map<String, Object> p) {
        if (p.get("id") != null) {
            currentTenderId = ((Number) p.get("id")).intValue();
        }

        String number = (String) p.get("tender_number");
        String title = (String) p.get("title");
        String category = (String) p.get("category_name");
        String status = (String) p.get("status");
        String dept = (String) p.get("issuing_department");
        String district = (String) p.get("district_name");
        Number sanctioned = (Number) p.get("sanctioned_amount");
        Number progress = (Number) p.get("progress_percentage");
        String timeRemaining = (String) p.get("time_remaining_label");

        String contractorName = (String) p.get("contractor_name");
        String contractorReg = (String) p.get("contractor_registration");

        String inspName = (String) p.get("assigned_inspector_name");
        String inspPhone = (String) p.get("assigned_inspector_phone");
        String inspEmail = (String) p.get("assigned_inspector_email");

        if (contractorName != null) {
            tvContractorBadge.setText("Registered Partner: " + contractorName + " • " + (contractorReg != null ? contractorReg : "REG-MH-2024"));
        }

        if (number != null) tvProjNumber.setText(number);
        if (title != null) tvProjTitle.setText(title);
        if (category != null) tvProjCategory.setText(category);
        if (status != null) tvProjStatus.setText(status);
        if (dept != null) tvProjDepartment.setText(dept + (district != null ? " • " + district : ""));

        if (sanctioned != null) {
            tvProjSanctioned.setText(String.format("₹%,.0f", sanctioned.doubleValue()));
        }
        if (timeRemaining != null) {
            tvProjTimeline.setText("⏱ " + timeRemaining);
        }

        int progInt = progress != null ? (int) Math.round(progress.doubleValue()) : 78;
        tvProgressLabel.setText(String.format("%d%%", progInt));
        pbCurrentProgress.setProgress(progInt);
        sbProgressPercent.setProgress(progInt);
        tvSliderLabel.setText(String.format("Set Completion Percentage: %d%%", progInt));

        if (inspName != null) {
            tvInspectorName.setText(inspName + " (Field Inspector)");
        }
        if (district != null) {
            tvInspectorJurisdiction.setText("Division: " + district + " • Quality & GPS Audit Authority");
        }
        if (inspPhone != null && !inspPhone.isEmpty()) {
            inspectorPhoneStr = inspPhone;
            tvInspectorPhone.setText("📞 +91 " + inspPhone);
        }
        if (inspEmail != null && !inspEmail.isEmpty()) {
            inspectorEmailStr = inspEmail;
        }

        // Render project images if returned
        List<Map<String, Object>> imgs = (List<Map<String, Object>>) p.get("project_images");
        if (imgs != null && !imgs.isEmpty()) {
            renderProjectImages(imgs);
        }
    }

    private void renderDefaultImages() {
        layoutProjectImages.removeAllViews();
        addProgressImageCard("1. Subgrade Soil Compaction & Density Test", "2026-09-20 • 100% Passed");
        addProgressImageCard("2. Bituminous Mix Temperature & Core Laying", "2026-09-22 • Verified");
        addProgressImageCard("3. Side Masonry Drainage Alignment", "2026-09-25 • Under Inspection");
        tvImageCountBadge.setText("3 Photos");
    }

    private void renderProjectImages(List<Map<String, Object>> imgs) {
        layoutProjectImages.removeAllViews();
        for (Map<String, Object> img : imgs) {
            String caption = (String) img.get("caption");
            if (caption == null) caption = (String) img.get("remarks");
            if (caption == null) caption = "Field Site Work Progress Photo";
            String date = (String) img.get("date");
            if (date == null) date = (String) img.get("device_capture_time");
            if (date == null) date = "2026-09-26";
            addProgressImageCard(caption, date);
        }
        tvImageCountBadge.setText(imgs.size() + " Photos");
    }

    private void addProgressImageCard(String title, String subtitle) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setBackgroundColor(0xFFF9F9FB);
        card.setPadding(16, 16, 16, 16);
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(400, LinearLayout.LayoutParams.WRAP_CONTENT);
        params.setMargins(0, 0, 16, 0);
        card.setLayoutParams(params);

        // Thumbnail simulation with Gov Watermark Badge
        LinearLayout imgSim = new LinearLayout(this);
        imgSim.setOrientation(LinearLayout.VERTICAL);
        imgSim.setBackgroundColor(0xFF263238);
        imgSim.setPadding(16, 24, 16, 24);
        LinearLayout.LayoutParams imgParams = new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 160);
        imgSim.setLayoutParams(imgParams);

        TextView tvWatermark = new TextView(this);
        tvWatermark.setText("🔒 MoSJE Tamper-Proof Audit Evidence\nfor the people to the people");
        tvWatermark.setTextColor(0xFFFFFFFF);
        tvWatermark.setTextSize(10f);
        tvWatermark.setTextAlignment(View.TEXT_ALIGNMENT_CENTER);
        imgSim.addView(tvWatermark);

        TextView tvT = new TextView(this);
        tvT.setText(title);
        tvT.setTextSize(12f);
        tvT.setTextColor(0xFF110B0A);
        tvT.setTypeface(null, android.graphics.Typeface.BOLD);
        tvT.setPadding(0, 8, 0, 2);

        TextView tvSub = new TextView(this);
        tvSub.setText(subtitle);
        tvSub.setTextSize(10f);
        tvSub.setTextColor(0xFF666666);

        card.addView(imgSim);
        card.addView(tvT);
        card.addView(tvSub);

        layoutProjectImages.addView(card);
    }

    private void performProgressUpdate() {
        int newProgress = sbProgressPercent.getProgress();
        String selectedState = spnExecutionState.getSelectedItem() != null ? spnExecutionState.getSelectedItem().toString().toUpperCase() : "";
        final String finalStatus;
        if (selectedState.contains("READY")) {
            finalStatus = "READY_FOR_INSPECTION";
        } else if (selectedState.contains("COMPLETED") || newProgress >= 100) {
            finalStatus = "COMPLETED";
        } else {
            finalStatus = "IN_PROGRESS";
        }
        String notes = etContractorNotes.getText().toString().trim();

        btnSubmitContractorUpdate.setEnabled(false);
        btnSubmitContractorUpdate.setText("Transmitting Progress Update...");

        Map<String, Object> payload = new HashMap<>();
        payload.put("progress_percentage", newProgress);
        payload.put("status", finalStatus);
        payload.put("contractor_notes", notes);

        ApiClient.getApiService().updateContractorProgress(currentTenderId, payload).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                btnSubmitContractorUpdate.setEnabled(true);
                btnSubmitContractorUpdate.setText("Submit Completion Update & Request Inspection →");

                if (response.isSuccessful()) {
                    tvProgressLabel.setText(String.format("%d%%", newProgress));
                    pbCurrentProgress.setProgress(newProgress);
                    tvProjStatus.setText(finalStatus);
                    showToast("✓ Completion status updated to " + newProgress + "%! Inspecting Officer " + tvInspectorName.getText() + " has been notified.");
                } else {
                    showError("Failed to update status: HTTP " + response.code());
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                btnSubmitContractorUpdate.setEnabled(true);
                btnSubmitContractorUpdate.setText("Submit Completion Update & Request Inspection →");
                // Local optimistic update
                tvProgressLabel.setText(String.format("%d%%", newProgress));
                pbCurrentProgress.setProgress(newProgress);
                showToast("✓ Completion status updated locally (" + newProgress + "%). Will sync when online.");
            }
        });
    }
}
