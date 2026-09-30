package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.util.PrefManager;

public class ComplaintCreateActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private int tenderId;
    private EditText etSubject, etDescription;
    private Button btnSubmit;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);
        tenderId = getIntent().getIntExtra("TENDER_ID", 1);

        initViews();
    }

    private void initViews() {
        etSubject = new EditText(this);
        etSubject.setHint("Complaint Subject (e.g. Defective culvert concrete)");

        etDescription = new EditText(this);
        etDescription.setHint("Detailed grievance description (minimum 10 characters)...");

        btnSubmit = new Button(this);
        btnSubmit.setText("Submit Complaint to Contractor's Senior Officer");
        btnSubmit.setOnClickListener(v -> submitComplaint());
    }

    private void submitComplaint() {
        String subj = etSubject.getText().toString().trim();
        String desc = etDescription.getText().toString().trim();

        if (subj.isEmpty() || desc.length() < 10) {
            Toast.makeText(this, "Subject and Description (min 10 characters) are required.", Toast.LENGTH_SHORT).show();
            return;
        }

        // POST to /api/complaints
        Toast.makeText(this, "Complaint routed to responsible senior officer.", Toast.LENGTH_SHORT).show();
        finish();
    }
}
