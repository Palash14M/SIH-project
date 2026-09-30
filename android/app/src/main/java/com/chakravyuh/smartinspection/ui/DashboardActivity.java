package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.util.PrefManager;

public class DashboardActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private TextView tvActiveTenders, tvVarianceAlerts, tvDelayedTenders, tvOverdueReinspections;
    private LinearLayout layoutDistrictCoverage;
    private Button btnDownloadPdf, btnExportCsv;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        initViews();
        loadDashboardSummary();
    }

    private void initViews() {
        tvActiveTenders = new TextView(this);
        tvVarianceAlerts = new TextView(this);
        tvDelayedTenders = new TextView(this);
        tvOverdueReinspections = new TextView(this);

        layoutDistrictCoverage = new LinearLayout(this);
        layoutDistrictCoverage.setOrientation(LinearLayout.VERTICAL);

        btnDownloadPdf = new Button(this);
        btnDownloadPdf.setText("Download Inspection PDF Report");
        btnDownloadPdf.setOnClickListener(v -> Toast.makeText(this, "Downloading PDF report...", Toast.LENGTH_SHORT).show());

        btnExportCsv = new Button(this);
        btnExportCsv.setText("Export Tenders CSV");
        btnExportCsv.setOnClickListener(v -> Toast.makeText(this, "Exporting CSV summary...", Toast.LENGTH_SHORT).show());
    }

    private void loadDashboardSummary() {
        // Calls GET /api/dashboard/summary
        // Populates metrics and district coverage table
    }
}
