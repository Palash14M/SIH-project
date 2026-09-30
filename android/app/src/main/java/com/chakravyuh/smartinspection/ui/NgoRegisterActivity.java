package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import androidx.appcompat.app.AlertDialog;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.network.ApiClient;
import java.util.HashMap;
import java.util.Map;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class NgoRegisterActivity extends BaseActivity {
    private EditText etOrgName;
    private EditText etRegNumber;
    private EditText etContactPerson;
    private EditText etMobile;
    private EditText etEmail;
    private EditText etPassword;
    private EditText etDistrictId;
    private EditText etAddress;
    private Button btnRegister;
    private TextView tvBackToLogin;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_ngo_register);

        initViews();
        setupEvents();
    }

    private void initViews() {
        etOrgName = findViewById(R.id.etOrgName);
        etRegNumber = findViewById(R.id.etRegNumber);
        etContactPerson = findViewById(R.id.etContactPerson);
        etMobile = findViewById(R.id.etMobile);
        etEmail = findViewById(R.id.etEmail);
        etPassword = findViewById(R.id.etPassword);
        etDistrictId = findViewById(R.id.etDistrictId);
        etAddress = findViewById(R.id.etAddress);
        btnRegister = findViewById(R.id.btnRegister);
        tvBackToLogin = findViewById(R.id.tvBackToLogin);
    }

    private void setupEvents() {
        btnRegister.setOnClickListener(v -> submitRegistration());
        tvBackToLogin.setOnClickListener(v -> finish());
    }

    private void submitRegistration() {
        String orgName = etOrgName.getText().toString().trim();
        String regNum = etRegNumber.getText().toString().trim();
        String contactPerson = etContactPerson.getText().toString().trim();
        String mobile = etMobile.getText().toString().trim();
        String email = etEmail.getText().toString().trim();
        String password = etPassword.getText().toString().trim();
        String districtStr = etDistrictId.getText().toString().trim();
        String address = etAddress.getText().toString().trim();

        if (orgName.isEmpty() || regNum.isEmpty() || contactPerson.isEmpty() ||
            mobile.length() < 10 || email.isEmpty() || password.length() < 6 ||
            districtStr.isEmpty() || address.isEmpty()) {
            showError("Please fill all required fields correctly (Mobile: 10 digits, Password: min 6 chars).");
            return;
        }

        int districtId;
        try {
            districtId = Integer.parseInt(districtStr);
        } catch (NumberFormatException e) {
            showError("Invalid district ID.");
            return;
        }

        btnRegister.setEnabled(false);
        btnRegister.setText("Submitting Application...");

        Map<String, Object> req = new HashMap<>();
        req.put("organization_name", orgName);
        req.put("registration_number", regNum);
        req.put("contact_person", contactPerson);
        req.put("mobile", mobile);
        req.put("email", email);
        req.put("password", password);
        req.put("district_id", districtId);
        req.put("state_id", 1);
        req.put("address", address);

        ApiClient.getApiService().registerNgo(req).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                btnRegister.setEnabled(true);
                btnRegister.setText("Submit Registration");

                if (response.isSuccessful() && response.body() != null) {
                    new AlertDialog.Builder(NgoRegisterActivity.this)
                        .setTitle("Application Registered")
                        .setMessage("Your NGO registration was submitted successfully with status PENDING. The District Officer will review your credentials before account activation.")
                        .setPositiveButton("Go to Login", (dialog, which) -> finish())
                        .setCancelable(false)
                        .show();
                } else {
                    showError("Registration failed. Please ensure the email and registration number are not already in use.");
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                btnRegister.setEnabled(true);
                btnRegister.setText("Submit Registration");
                showError("Network connection error: " + t.getMessage());
            }
        });
    }
}
