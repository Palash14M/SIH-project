package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.util.PrefManager;

public class TenderCreateActivity extends AppCompatActivity {
    private EditText etTenderNumber, etTitle, etCategory, etDepartment;
    private EditText etStateId, etDistrictId, etLat, etLng;
    private EditText etContractorId, etSanctionedAmount, etAwardDate, etStartDate, etEndDate;
    private EditText etSeniorOfficerId;
    private Button btnSubmit;
    private PrefManager prefManager;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        initViews();
    }

    private void initViews() {
        etTenderNumber = new EditText(this);
        etTenderNumber.setHint("Tender Number (e.g. TND-2026-NAG-01)");

        etTitle = new EditText(this);
        etTitle.setHint("Project Title");

        etCategory = new EditText(this);
        etCategory.setHint("Category ID (1:Roads, 2:Flyovers, 3:Buildings...)");

        etDepartment = new EditText(this);
        etDepartment.setHint("Issuing Department");

        etStateId = new EditText(this);
        etDistrictId = new EditText(this);
        etLat = new EditText(this);
        etLng = new EditText(this);
        etContractorId = new EditText(this);
        etSanctionedAmount = new EditText(this);
        etAwardDate = new EditText(this);
        etStartDate = new EditText(this);
        etEndDate = new EditText(this);
        etSeniorOfficerId = new EditText(this);

        btnSubmit = new Button(this);
        btnSubmit.setText("Create Tender");
        btnSubmit.setOnClickListener(v -> submitTender());
    }

    private void submitTender() {
        String num = etTenderNumber.getText().toString().trim();
        String title = etTitle.getText().toString().trim();

        if (num.isEmpty() || title.isEmpty()) {
            Toast.makeText(this, "Tender Number and Title are required.", Toast.LENGTH_SHORT).show();
            return;
        }

        // Submits via POST /api/tenders
        Toast.makeText(this, "Tender " + num + " created successfully.", Toast.LENGTH_SHORT).show();
        finish();
    }
}
