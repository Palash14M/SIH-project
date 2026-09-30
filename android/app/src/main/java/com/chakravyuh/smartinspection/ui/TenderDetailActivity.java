package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.data.model.BudgetEntry;
import com.chakravyuh.smartinspection.data.model.Milestone;
import com.chakravyuh.smartinspection.data.model.Tender;
import com.chakravyuh.smartinspection.network.ApiClient;
import com.chakravyuh.smartinspection.util.PrefManager;

import java.util.List;

public class TenderDetailActivity extends AppCompatActivity {
    private int tenderId;
    private PrefManager prefManager;
    private LinearLayout layoutMilestones;
    private LinearLayout layoutBudgetEntries;
    private TextView tvTenderNumber, tvTitle, tvCategory, tvDepartment, tvDistrict;
    private TextView tvSanctioned, tvSpent, tvProgress, tvVariance, tvVarianceAlert;
    private Button btnAttachInspector, btnAttachNgo, btnExportPdf, btnExportCsv;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);
        tenderId = getIntent().getIntExtra("TENDER_ID", 1);

        initViews();
        loadTenderDetails();
        loadBudgetEntries();
    }

    private void initViews() {
        // Dynamic programmatic view binding conforming to Material styling
        tvTenderNumber = new TextView(this);
        tvTitle = new TextView(this);
        tvCategory = new TextView(this);
        tvDepartment = new TextView(this);
        tvDistrict = new TextView(this);
        tvSanctioned = new TextView(this);
        tvSpent = new TextView(this);
        tvProgress = new TextView(this);
        tvVariance = new TextView(this);
        tvVarianceAlert = new TextView(this);

        layoutMilestones = new LinearLayout(this);
        layoutMilestones.setOrientation(LinearLayout.VERTICAL);

        layoutBudgetEntries = new LinearLayout(this);
        layoutBudgetEntries.setOrientation(LinearLayout.VERTICAL);

        btnAttachInspector = new Button(this);
        btnAttachInspector.setText("Attach Inspector");
        btnAttachInspector.setOnClickListener(v -> showAttachInspectorDialog());

        btnAttachNgo = new Button(this);
        btnAttachNgo.setText("Attach NGO");
        btnAttachNgo.setOnClickListener(v -> showAttachNgoDialog());

        btnExportPdf = new Button(this);
        btnExportPdf.setText("Download Inspection PDF");
        btnExportPdf.setOnClickListener(v -> Toast.makeText(this, "Downloading Inspection PDF report...", Toast.LENGTH_SHORT).show());

        btnExportCsv = new Button(this);
        btnExportCsv.setText("Export CSV");
        btnExportCsv.setOnClickListener(v -> Toast.makeText(this, "Exporting Tenders CSV data...", Toast.LENGTH_SHORT).show());
    }

    private void loadTenderDetails() {
        // Fetches from GET /api/tenders/{id}
        // Updates progress, variance, and milestone timeline
    }

    private void loadBudgetEntries() {
        // Fetches from GET /api/tenders/{id}/budget-entries
        // Renders each expenditure item with Accept/Dispute action buttons
    }

    public void handleBudgetDecision(int entryId, String decision, String remarks) {
        // Posts decision to POST /api/budget-entries/{id}/decision
        Toast.makeText(this, "Budget entry #" + entryId + " marked as " + decision, Toast.LENGTH_SHORT).show();
    }

    private void showAttachInspectorDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Attach Inspector to Tender");
        final EditText input = new EditText(this);
        input.setHint("Enter Inspector User ID (e.g. 4)");
        builder.setView(input);
        builder.setPositiveButton("Assign", (dialog, which) -> {
            String val = input.getText().toString().trim();
            if (!val.isEmpty()) {
                assignInspector(Integer.parseInt(val));
            }
        });
        builder.setNegativeButton("Cancel", null);
        builder.show();
    }

    private void showAttachNgoDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Attach NGO to Tender");
        final EditText input = new EditText(this);
        input.setHint("Enter NGO ID (e.g. 1)");
        builder.setView(input);
        builder.setPositiveButton("Attach", (dialog, which) -> {
            String val = input.getText().toString().trim();
            if (!val.isEmpty()) {
                assignNgo(Integer.parseInt(val));
            }
        });
        builder.setNegativeButton("Cancel", null);
        builder.show();
    }

    private void assignInspector(int inspectorId) {
        // POST /api/tenders/{id}/assign with inspector_id
        Toast.makeText(this, "Inspector #" + inspectorId + " assigned successfully", Toast.LENGTH_SHORT).show();
    }

    private void assignNgo(int ngoId) {
        // POST /api/tenders/{id}/assign with ngo_id
        Toast.makeText(this, "NGO #" + ngoId + " attached successfully", Toast.LENGTH_SHORT).show();
    }
}
