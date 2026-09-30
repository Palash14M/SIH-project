package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.util.PrefManager;

public class EscalationPaymentActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private int complaintId;
    private TextView tvEscalationTarget, tvBaseFee, tvGst, tvTotalFee, tvPolicyNotice, tvReceipt;
    private Button btnPayAndEscalate;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);
        complaintId = getIntent().getIntExtra("COMPLAINT_ID", 1);

        initViews();
    }

    private void initViews() {
        setContentView(R.layout.activity_tender_detail);

        tvEscalationTarget = new TextView(this);
        tvEscalationTarget.setText("Target Escalation Authority: District Officer (DO)");

        tvBaseFee = new TextView(this);
        tvBaseFee.setText("Base Statutory Fee: ₹400.00");

        tvGst = new TextView(this);
        tvGst.setText("GST (18% CGST + SGST): ₹72.00");

        tvTotalFee = new TextView(this);
        tvTotalFee.setText("Total Payable: ₹472.00");

        tvPolicyNotice = new TextView(this);
        tvPolicyNotice.setText("MANDATORY RULE: Under MoSJE Anti-Fraud guidelines, this deposit of ₹472.00 prevents malicious delays. If your grievance is UPHELD by the authority, this fee will be 100% AUTOMATICALLY REFUNDED. If rejected, it will be retained.");

        btnPayAndEscalate = new Button(this);
        btnPayAndEscalate.setText("Pay ₹472.00 & Submit Escalation");
        btnPayAndEscalate.setOnClickListener(v -> processEscalationPayment());
    }

    private void processEscalationPayment() {
        // Backend API call: POST /api/complaints/{id}/escalate with confirm_payment=true
        Toast.makeText(this, "Payment Order ORD-" + System.currentTimeMillis() + " processed successfully! Grievance escalated.", Toast.LENGTH_LONG).show();
        finish();
    }
}
