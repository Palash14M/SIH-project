package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.data.model.Complaint;
import com.chakravyuh.smartinspection.util.PrefManager;

public class ComplaintActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private int complaintId;
    private TextView tvHeader, tvStatus, tvLevel, tvSubject, tvDesc, tvRemarks, tvRefundNotice;
    private Button btnEscalate;
    private LinearLayout layoutTimeline;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);
        complaintId = getIntent().getIntExtra("COMPLAINT_ID", 1);

        initViews();
        loadComplaintData();
    }

    private void initViews() {
        setContentView(R.layout.activity_tender_detail); // standard themed layout container

        tvHeader = new TextView(this);
        tvHeader.setText("Grievance Tracking & Redressal");

        tvStatus = new TextView(this);
        tvLevel = new TextView(this);
        tvSubject = new TextView(this);
        tvDesc = new TextView(this);
        tvRemarks = new TextView(this);
        tvRefundNotice = new TextView(this);

        btnEscalate = new Button(this);
        btnEscalate.setText("Escalate Grievance to Next Authority (Fee: ₹400 + GST)");
        btnEscalate.setOnClickListener(v -> {
            Intent intent = new Intent(this, EscalationPaymentActivity.class);
            intent.putExtra("COMPLAINT_ID", complaintId);
            startActivity(intent);
        });
    }

    private void loadComplaintData() {
        // Mock / API call GET /api/complaints/{id}
        tvSubject.setText("Subject: Structural Cracks on Culvert Wing Wall");
        tvStatus.setText("Status: REJECTED by Senior Officer");
        tvLevel.setText("Current Level: SENIOR_OFFICER (Executive Engineer)");
        tvRemarks.setText("Officer Remark: Site inspection reported hair-line surface curing cracks only.");
        
        // Show escalate button if rejected and not admin
        btnEscalate.setVisibility(View.VISIBLE);
    }
}
