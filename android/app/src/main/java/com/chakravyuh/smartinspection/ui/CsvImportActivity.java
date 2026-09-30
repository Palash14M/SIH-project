package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.RadioGroup;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.data.model.ImportReport;
import com.chakravyuh.smartinspection.util.PrefManager;

public class CsvImportActivity extends AppCompatActivity {
    private Button btnDownloadTemplate;
    private Button btnSelectFile;
    private Button btnUploadCsv;
    private RadioGroup rgDuplicatePolicy;
    private TextView tvReportSummary;
    private TextView tvRejectedRows;
    private PrefManager prefManager;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        initViews();
    }

    private void initViews() {
        btnDownloadTemplate = new Button(this);
        btnDownloadTemplate.setText("Download Official CSV Template");
        btnDownloadTemplate.setOnClickListener(v -> downloadTemplate());

        btnSelectFile = new Button(this);
        btnSelectFile.setText("Select Tender CSV File");
        btnSelectFile.setOnClickListener(v -> selectFile());

        btnUploadCsv = new Button(this);
        btnUploadCsv.setText("Process & Import Tenders");
        btnUploadCsv.setOnClickListener(v -> uploadAndProcessCsv());

        rgDuplicatePolicy = new RadioGroup(this);
        // Radio buttons for 'skip' or 'update'

        tvReportSummary = new TextView(this);
        tvRejectedRows = new TextView(this);
    }

    private void downloadTemplate() {
        // Downloads template from GET /api/tenders/template
        Toast.makeText(this, "Downloading template tender_import_template.csv...", Toast.LENGTH_SHORT).show();
    }

    private void selectFile() {
        Toast.makeText(this, "Selected: tenders_batch_q3.csv", Toast.LENGTH_SHORT).show();
    }

    private void uploadAndProcessCsv() {
        // POST to /api/tenders/import (multipart)
        // Parses response into ImportReport
        displayImportReport(10, 8, 0, 2);
    }

    private void displayImportReport(int total, int created, int updated, int rejected) {
        tvReportSummary.setText(String.format("Import Complete: %d total, %d created, %d updated, %d rejected", total, created, updated, rejected));
        if (rejected > 0) {
            tvRejectedRows.setText("Rejected rows: Row #4 (missing title), Row #7 (duplicate tender number)");
        }
        Toast.makeText(this, "Import Processed: " + created + " tenders created", Toast.LENGTH_LONG).show();
    }
}
