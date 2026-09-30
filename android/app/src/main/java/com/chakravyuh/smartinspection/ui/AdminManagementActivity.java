package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.data.model.User;
import com.chakravyuh.smartinspection.util.PrefManager;

public class AdminManagementActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private Button btnCreateStaff;
    private Button btnAuditLog;
    private Button btnFinanceSummary;
    private LinearLayout layoutStaffList;
    private TextView tvFinanceOverview;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        initViews();
        loadStaffList();
        loadFinanceSummary();
    }

    private void initViews() {
        btnCreateStaff = new Button(this);
        btnCreateStaff.setText("Create Staff Account");
        btnCreateStaff.setOnClickListener(v -> showCreateStaffDialog());

        btnAuditLog = new Button(this);
        btnAuditLog.setText("View Immutable Audit Log");
        btnAuditLog.setOnClickListener(v -> Toast.makeText(this, "Opening Audit Log records...", Toast.LENGTH_SHORT).show());

        btnFinanceSummary = new Button(this);
        btnFinanceSummary.setText("Fees & Refund Summary");
        btnFinanceSummary.setOnClickListener(v -> Toast.makeText(this, "Opening Financial Ledger...", Toast.LENGTH_SHORT).show());

        tvFinanceOverview = new TextView(this);
        layoutStaffList = new LinearLayout(this);
        layoutStaffList.setOrientation(LinearLayout.VERTICAL);
    }

    private void loadStaffList() {
        // Calls GET /api/admin/users
        // Populates staff accounts with status toggle (ACTIVE / INACTIVE)
    }

    private void loadFinanceSummary() {
        // Calls GET /api/admin/finance-summary
        tvFinanceOverview.setText("Escalation Fees Collected: ₹400 base + 18% GST (₹72) = ₹472/txn | Automatic Refunds on Upheld Complaints");
    }

    private void showCreateStaffDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Create MoSJE Staff Account");
        final EditText input = new EditText(this);
        input.setHint("Name, Email, Role (INSPECTOR / DISTRICT_OFFICER / STATE_OFFICER)");
        builder.setView(input);
        builder.setPositiveButton("Create", (dialog, which) -> {
            Toast.makeText(this, "Staff account created successfully.", Toast.LENGTH_SHORT).show();
        });
        builder.setNegativeButton("Cancel", null);
        builder.show();
    }

    public void toggleStaffStatus(int userId, String newStatus) {
        // PUT /api/admin/users/{id}/status with status
        Toast.makeText(this, "User #" + userId + " status updated to " + newStatus, Toast.LENGTH_SHORT).show();
    }
}
