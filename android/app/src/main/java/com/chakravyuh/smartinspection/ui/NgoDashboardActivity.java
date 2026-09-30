package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.util.PrefManager;

public class NgoDashboardActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private TextView tvOrgName, tvStatusBanner;
    private LinearLayout layoutAttachedTenders;
    private LinearLayout layoutComplaints;
    private Button btnRaiseComplaint;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        initViews();
        loadDashboardData();
    }

    private void initViews() {
        tvOrgName = new TextView(this);
        tvOrgName.setText("Sewa Bharati Trust (Approved Social Auditor)");

        tvStatusBanner = new TextView(this);
        tvStatusBanner.setText("Status: APPROVED • Authorized to monitor attached MoSJE projects & raise complaints");

        layoutAttachedTenders = new LinearLayout(this);
        layoutAttachedTenders.setOrientation(LinearLayout.VERTICAL);

        layoutComplaints = new LinearLayout(this);
        layoutComplaints.setOrientation(LinearLayout.VERTICAL);

        btnRaiseComplaint = new Button(this);
        btnRaiseComplaint.setText("Raise Grievance / Complaint");
        btnRaiseComplaint.setOnClickListener(v -> {
            Toast.makeText(this, "Opening Complaint Creation Form...", Toast.LENGTH_SHORT).show();
        });
    }

    private void loadDashboardData() {
        // Calls GET /api/tenders?attached_only=1 (returns tenders with contractor phone, email, address)
        // Calls GET /api/complaints
        // Displays refund statuses (₹472.00 refunded on upheld escalations)
    }
}
