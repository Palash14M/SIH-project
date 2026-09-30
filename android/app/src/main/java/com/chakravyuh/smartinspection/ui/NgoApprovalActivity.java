package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.data.model.Ngo;
import com.chakravyuh.smartinspection.util.PrefManager;

import java.util.List;

public class NgoApprovalActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private LinearLayout layoutPendingNgos;
    private TextView tvHeader;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        initViews();
        loadPendingNgos();
    }

    private void initViews() {
        tvHeader = new TextView(this);
        tvHeader.setText("Pending NGO Registrations for Jurisdiction");

        layoutPendingNgos = new LinearLayout(this);
        layoutPendingNgos.setOrientation(LinearLayout.VERTICAL);
    }

    private void loadPendingNgos() {
        // Calls GET /api/ngos/pending
        // Populates list with organization name, registration number, contact person, document path
    }

    public void approveNgo(int ngoId) {
        // Calls POST /api/ngos/{id}/decision status=APPROVED
        Toast.makeText(this, "NGO #" + ngoId + " approved successfully.", Toast.LENGTH_SHORT).show();
    }

    public void rejectNgo(int ngoId) {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Reject NGO Registration");
        final EditText input = new EditText(this);
        input.setHint("Enter mandatory reason for rejection...");
        builder.setView(input);
        builder.setPositiveButton("Reject", (dialog, which) -> {
            String reason = input.getText().toString().trim();
            if (!reason.isEmpty()) {
                // Calls POST /api/ngos/{id}/decision status=REJECTED, reason
                Toast.makeText(this, "NGO #" + ngoId + " rejected. Reason: " + reason, Toast.LENGTH_SHORT).show();
            }
        });
        builder.setNegativeButton("Cancel", null);
        builder.show();
    }
}
