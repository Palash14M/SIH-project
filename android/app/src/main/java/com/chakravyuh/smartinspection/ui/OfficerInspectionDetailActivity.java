package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.data.model.Inspection;
import com.chakravyuh.smartinspection.util.PrefManager;

public class OfficerInspectionDetailActivity extends AppCompatActivity {
    private int inspectionId;
    private PrefManager prefManager;
    private TextView tvStatus, tvInspector, tvTenderNumber, tvRemarks;
    private TextView tvProgress, tvSpent, tvVariance;
    private LinearLayout layoutEvidenceGallery;
    private LinearLayout layoutQualityChecks;
    private Button btnVerify, btnRaiseIssue, btnNotifyContractor, btnScheduleReinspection;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);
        inspectionId = getIntent().getIntExtra("INSPECTION_ID", 1);

        initViews();
        loadInspectionDetails();
    }

    private void initViews() {
        tvStatus = new TextView(this);
        tvInspector = new TextView(this);
        tvTenderNumber = new TextView(this);
        tvRemarks = new TextView(this);
        tvProgress = new TextView(this);
        tvSpent = new TextView(this);
        tvVariance = new TextView(this);

        layoutEvidenceGallery = new LinearLayout(this);
        layoutEvidenceGallery.setOrientation(LinearLayout.HORIZONTAL);

        layoutQualityChecks = new LinearLayout(this);
        layoutQualityChecks.setOrientation(LinearLayout.VERTICAL);

        btnVerify = new Button(this);
        btnVerify.setText("Verify & Approve");
        btnVerify.setOnClickListener(v -> verifyInspection());

        btnRaiseIssue = new Button(this);
        btnRaiseIssue.setText("Raise Issue");
        btnRaiseIssue.setOnClickListener(v -> showRaiseIssueDialog());

        btnNotifyContractor = new Button(this);
        btnNotifyContractor.setText("Notify Contractor");
        btnNotifyContractor.setOnClickListener(v -> notifyContractor());

        btnScheduleReinspection = new Button(this);
        btnScheduleReinspection.setText("Schedule Re-inspection");
        btnScheduleReinspection.setOnClickListener(v -> showScheduleReinspectionDialog());
    }

    private void loadInspectionDetails() {
        // Calls GET /api/inspections/{id}
        // Loads evidence items with burned watermark, GPS lat/lng, accuracy, device time, server time, time mismatch
    }

    private void verifyInspection() {
        // POST /api/inspections/{id}/verify action=VERIFY
        Toast.makeText(this, "Inspection #" + inspectionId + " marked as VERIFIED", Toast.LENGTH_SHORT).show();
    }

    private void showRaiseIssueDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Raise Issue on Inspection");
        final EditText input = new EditText(this);
        input.setHint("Enter defect remarks and required corrective action...");
        builder.setView(input);
        builder.setPositiveButton("Raise Issue", (dialog, which) -> {
            String remarks = input.getText().toString().trim();
            if (!remarks.isEmpty()) {
                raiseIssue(remarks);
            }
        });
        builder.setNegativeButton("Cancel", null);
        builder.show();
    }

    private void raiseIssue(String remarks) {
        // POST /api/inspections/{id}/verify action=RAISE_ISSUE
        Toast.makeText(this, "Issue raised for Inspection #" + inspectionId, Toast.LENGTH_SHORT).show();
    }

    private void notifyContractor() {
        // POST /api/inspections/{id}/notify
        Toast.makeText(this, "Contractor and Responsible Senior notified.", Toast.LENGTH_SHORT).show();
    }

    private void showScheduleReinspectionDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Schedule Re-inspection");
        final EditText input = new EditText(this);
        input.setHint("Scheduled Date (YYYY-MM-DD)");
        builder.setView(input);
        builder.setPositiveButton("Schedule", (dialog, which) -> {
            String date = input.getText().toString().trim();
            if (!date.isEmpty()) {
                scheduleReinspection(date, 4);
            }
        });
        builder.setNegativeButton("Cancel", null);
        builder.show();
    }

    private void scheduleReinspection(String date, int inspectorId) {
        // POST /api/inspections/{id}/schedule-reinspection
        Toast.makeText(this, "Re-inspection scheduled for " + date + " assigned to Inspector #" + inspectorId, Toast.LENGTH_SHORT).show();
    }
}
