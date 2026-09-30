package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.util.PrefManager;

public class PublicNudgeActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private int tenderId;
    private String tenderTitle;
    private String ngoName;

    private TextView tvTenderInfo, tvDailyStatusNotice, tvWarningNotice;
    private EditText etReason;
    private Button btnSubmitNudge;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        tenderId = getIntent().getIntExtra("TENDER_ID", 1);
        tenderTitle = getIntent().getStringExtra("TENDER_TITLE");
        ngoName = getIntent().getStringExtra("NGO_NAME");

        initViews();
        checkDailyNudgeQuota();
    }

    private void initViews() {
        setContentView(R.layout.activity_tender_detail);

        tvTenderInfo = new TextView(this);
        tvTenderInfo.setText("Nudging Attached NGO for Project:\n" + (tenderTitle != null ? tenderTitle : "Public Works Tender"));

        tvDailyStatusNotice = new TextView(this);
        tvDailyStatusNotice.setText("Daily Quota: 1 Nudge Available Today");

        tvWarningNotice = new TextView(this);
        tvWarningNotice.setText("CRITICAL CITIZEN AUDIT NOTICE:\n" +
                "1. Each citizen is permitted strictly ONE nudge per calendar day across all projects.\n" +
                "2. A constructive reason of at least 10 characters is mandatory.\n" +
                "3. IF YOU SUBMIT WITH AN EMPTY OR SUB-10-CHARACTER REASON, YOUR DAILY CHANCE WILL BE BURNED IMMEDIATELY AND NO NUDGE WILL BE DELIVERED.");

        etReason = new EditText(this);
        etReason.setHint("State why the NGO should review this project (minimum 10 characters)...");

        btnSubmitNudge = new Button(this);
        btnSubmitNudge.setText("Submit Citizen Nudge");
        btnSubmitNudge.setOnClickListener(v -> submitNudge());
    }

    private void checkDailyNudgeQuota() {
        // Backend API check: GET /api/nudges/status
        // If has_nudged_today == true, disable button and show notice
    }

    private void submitNudge() {
        String reason = etReason.getText().toString().trim();
        // Backend API call: POST /api/nudges
        // Payload: {"tender_id": tenderId, "reason": reason}
        if (reason.length() < 10) {
            Toast.makeText(this, "Reason must be at least 10 characters. Submitting invalid reason will burn your daily chance!", Toast.LENGTH_LONG).show();
            // User can still choose to submit to test chance burning behavior
        }
        Toast.makeText(this, "Nudge request processed by server.", Toast.LENGTH_SHORT).show();
        finish();
    }
}
