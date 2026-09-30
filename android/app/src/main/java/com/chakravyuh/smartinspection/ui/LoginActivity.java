package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.Spinner;
import android.widget.TextView;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.network.ApiClient;
import com.chakravyuh.smartinspection.util.Constants;
import java.util.HashMap;
import java.util.Map;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class LoginActivity extends BaseActivity {
    private Spinner spnPosition;
    private TextView tvPositionBanner;
    private LinearLayout layoutStaffLogin;
    private LinearLayout layoutPublicOtp;

    // Staff fields
    private EditText etEmail;
    private EditText etPassword;
    private EditText etTotpCode;
    private Button btnStaffLogin;

    // Public OTP fields
    private EditText etPhone;
    private EditText etOtp;
    private Button btnRequestOtp;
    private Button btnVerifyOtp;
    private TextView tvOtpHint;

    // Server configuration
    private TextView tvServerStatus;
    private Button btnChangeServer;

    private static final String[] POSITIONS = {
        "1. Field Inspector",
        "2. District Officer",
        "3. State Officer",
        "4. MoSJE Admin (Ministry)",
        "5. Master Admin (Supreme)",
        "6. NGO / Social Auditor",
        "7. Contractor / Agency Partner",
        "8. Public Citizen"
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_login);

        initViews();
        setupPositionDropdown();
        setupEvents();
    }

    private void initViews() {
        spnPosition = findViewById(R.id.spnPosition);
        tvPositionBanner = findViewById(R.id.tvPositionBanner);
        layoutStaffLogin = findViewById(R.id.layoutStaffLogin);
        layoutPublicOtp = findViewById(R.id.layoutPublicOtp);

        etEmail = findViewById(R.id.etEmail);
        etPassword = findViewById(R.id.etPassword);
        etTotpCode = findViewById(R.id.etTotpCode);
        btnStaffLogin = findViewById(R.id.btnStaffLogin);

        etPhone = findViewById(R.id.etPhone);
        etOtp = findViewById(R.id.etOtp);
        btnRequestOtp = findViewById(R.id.btnRequestOtp);
        btnVerifyOtp = findViewById(R.id.btnVerifyOtp);
        tvOtpHint = findViewById(R.id.tvOtpHint);

        tvServerStatus = findViewById(R.id.tvServerStatus);
        btnChangeServer = findViewById(R.id.btnChangeServer);
        updateServerStatusDisplay();

        if (btnChangeServer != null) {
            btnChangeServer.setOnClickListener(v -> showServerChangeDialog());
        }

        TextView tvNgoRegister = findViewById(R.id.tvNgoRegister);
        if (tvNgoRegister != null) {
            tvNgoRegister.setOnClickListener(v -> startActivity(new Intent(LoginActivity.this, NgoRegisterActivity.class)));
        }
    }

    private void setupPositionDropdown() {
        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, POSITIONS);
        spnPosition.setAdapter(adapter);

        spnPosition.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                onPositionSelected(position);
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }

    private void onPositionSelected(int positionIndex) {
        if (etTotpCode != null) etTotpCode.setText("");

        switch (positionIndex) {
            case 0: // Field Inspector
                layoutStaffLogin.setVisibility(View.VISIBLE);
                layoutPublicOtp.setVisibility(View.GONE);
                tvPositionBanner.setText("Field Inspector • Mobile & Inspection Code Flow | Nagpur District");
                etEmail.setText("inspector.rajesh@mosje.gov.in");
                if (etPassword != null) etPassword.setText("demo@123");
                break;
            case 1: // District Officer
                layoutStaffLogin.setVisibility(View.VISIBLE);
                layoutPublicOtp.setVisibility(View.GONE);
                tvPositionBanner.setText("District Officer • LGD Sync: DL-LGD-478 | Nagpur Jurisdiction");
                etEmail.setText("district.nagpur@mosje.gov.in");
                if (etPassword != null) etPassword.setText("demo@123");
                break;
            case 2: // State Officer
                layoutStaffLogin.setVisibility(View.VISIBLE);
                layoutPublicOtp.setVisibility(View.GONE);
                tvPositionBanner.setText("State Officer • LGD Sync: ST-LGD-024 | Maharashtra State");
                etEmail.setText("state.mh@mosje.gov.in");
                if (etPassword != null) etPassword.setText("demo@123");
                break;
            case 3: // MoSJE Admin
                layoutStaffLogin.setVisibility(View.VISIBLE);
                layoutPublicOtp.setVisibility(View.GONE);
                tvPositionBanner.setText("MoSJE Admin • Central Gov-ID / @gov.in Sync | National Directorate");
                etEmail.setText("mosje.admin@gov.in");
                if (etPassword != null) etPassword.setText("demo@123");
                break;
            case 4: // Master Admin (Supreme)
                layoutStaffLogin.setVisibility(View.VISIBLE);
                layoutPublicOtp.setVisibility(View.GONE);
                tvPositionBanner.setText("Master Admin • Supreme Root Authority | All-Account Governance");
                etEmail.setText("admin");
                if (etPassword != null) etPassword.setText("admin");
                break;
            case 5: // NGO
                layoutStaffLogin.setVisibility(View.VISIBLE);
                layoutPublicOtp.setVisibility(View.GONE);
                tvPositionBanner.setText("NGO Social Auditor • Verified Attached Status | Public Grievances");
                etEmail.setText("demo.ngo@example.org");
                if (etPassword != null) etPassword.setText("demo@123");
                break;
            case 6: // Contractor / Agency Partner
                layoutStaffLogin.setVisibility(View.VISIBLE);
                layoutPublicOtp.setVisibility(View.GONE);
                tvPositionBanner.setText("Contractor • Project Completion Updates & Inspecting Officer Details");
                etEmail.setText("contractor@larseninfra.com");
                if (etPassword != null) etPassword.setText("demo@123");
                break;
            case 7: // Public Citizen
                layoutStaffLogin.setVisibility(View.GONE);
                layoutPublicOtp.setVisibility(View.VISIBLE);
                tvPositionBanner.setText("Public Citizen • Masked Privacy Portal | 1-per-day Nudge Access");
                etPhone.setText("9821004567");
                if (etOtp != null) {
                    etOtp.setVisibility(View.VISIBLE);
                    etOtp.setText("123456");
                }
                if (tvOtpHint != null) {
                    tvOtpHint.setVisibility(View.VISIBLE);
                    tvOtpHint.setText("Demo OTP: 123456");
                }
                if (btnVerifyOtp != null) {
                    btnVerifyOtp.setVisibility(View.VISIBLE);
                }
                break;
        }
    }

    private void setupEvents() {
        btnStaffLogin.setOnClickListener(v -> performStaffLogin());
        btnRequestOtp.setOnClickListener(v -> performRequestOtp());
        btnVerifyOtp.setOnClickListener(v -> performVerifyOtp());
    }

    private void performStaffLogin() {
        String email = etEmail.getText().toString().trim();
        String password = etPassword.getText().toString().trim();

        if (email.isEmpty() || password.isEmpty()) {
            showError("Please enter position credentials");
            return;
        }

        btnStaffLogin.setEnabled(false);
        btnStaffLogin.setText("Verifying Details...");

        Map<String, String> credentials = new HashMap<>();
        credentials.put("email", email);
        credentials.put("username", email);
        credentials.put("password", password);
        if (etTotpCode != null && !etTotpCode.getText().toString().trim().isEmpty()) {
            credentials.put("authenticator_code", etTotpCode.getText().toString().trim());
        }

        ApiClient.getApiService().login(credentials).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                btnStaffLogin.setEnabled(true);
                btnStaffLogin.setText("Verify Details & Enter Dashboard →");

                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    if (Boolean.TRUE.equals(body.get("success"))) {
                        Map<String, Object> data = (Map<String, Object>) body.get("data");
                        String token = (String) data.get("token");
                        Map<String, Object> user = (Map<String, Object>) data.get("user");

                        int userId = ((Number) user.get("id")).intValue();
                        String name = (String) user.get("name");
                        String role = (String) user.get("role");
                        Integer districtId = user.get("district_id") != null ? ((Number) user.get("district_id")).intValue() : null;
                        Integer stateId = user.get("state_id") != null ? ((Number) user.get("state_id")).intValue() : null;

                        preferenceManager.saveSession(token, userId, name, role, districtId, stateId);
                        if (Constants.ROLE_CONTRACTOR.equalsIgnoreCase(role)) {
                            showToast("✓ Verified! Entered Contractor Management Desk.");
                            startActivity(new Intent(LoginActivity.this, ContractorDashboardActivity.class));
                        } else {
                            showToast("✓ Verified! Entered " + role + " dashboard.");
                            startActivity(new Intent(LoginActivity.this, MainActivity.class));
                        }
                        finish();
                        return;
                    }
                }
                showError("Invalid credentials. Please verify your details.");
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                btnStaffLogin.setEnabled(true);
                btnStaffLogin.setText("Verify Details & Enter Dashboard →");
                String errorMsg = t.getMessage() != null ? t.getMessage() : "Unknown error";
                showError("Server Connection Failed: " + errorMsg);
                showServerChangeDialog();
            }
        });
    }

    private void performRequestOtp() {
        String phone = etPhone.getText().toString().trim();
        if (phone.length() < 10) {
            showError("Please enter a valid 10-digit mobile number");
            return;
        }

        btnRequestOtp.setEnabled(false);
        btnRequestOtp.setText("Sending...");

        Map<String, String> req = new HashMap<>();
        req.put("phone", phone);

        ApiClient.getApiService().requestOtp(req).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                btnRequestOtp.setEnabled(true);
                btnRequestOtp.setText("Resend OTP");

                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    Map<String, Object> data = (Map<String, Object>) body.get("data");
                    if (data != null && data.containsKey("demo_otp")) {
                        String demoOtp = (String) data.get("demo_otp");
                        tvOtpHint.setVisibility(View.VISIBLE);
                        tvOtpHint.setText(String.format("Demo OTP: %s", demoOtp));
                        // User requested passwords and OTPs must not be prefilled
                        etOtp.setText("");
                        etOtp.setHint(String.format("Enter OTP (Demo: %s)", demoOtp));
                    }
                    etOtp.setVisibility(View.VISIBLE);
                    btnVerifyOtp.setVisibility(View.VISIBLE);
                    showToast("OTP generated. Enter the code to verify.");
                } else {
                    showError("Failed to send OTP. Rate limit may apply (5/hr).");
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                btnRequestOtp.setEnabled(true);
                btnRequestOtp.setText("Send OTP");
                String errorMsg = t.getMessage() != null ? t.getMessage() : "Unknown error";
                showError("Server Connection Failed: " + errorMsg);
                showServerChangeDialog();
            }
        });
    }

    private void performVerifyOtp() {
        String phone = etPhone.getText().toString().trim();
        String otp = etOtp.getText().toString().trim();

        if (otp.length() < 4) {
            showError("Please enter the verification OTP");
            return;
        }

        btnVerifyOtp.setEnabled(false);
        btnVerifyOtp.setText("Verifying Details...");
        Map<String, String> req = new HashMap<>();
        req.put("phone", phone);
        req.put("otp", otp);

        ApiClient.getApiService().verifyOtp(req).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                btnVerifyOtp.setEnabled(true);
                btnVerifyOtp.setText("Verify Details & Enter Dashboard →");
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    Map<String, Object> data = (Map<String, Object>) body.get("data");
                    String token = (String) data.get("token");
                    Map<String, Object> user = (Map<String, Object>) data.get("user");

                    int userId = ((Number) user.get("id")).intValue();
                    String name = (String) user.get("name");

                    preferenceManager.saveSession(token, userId, name, Constants.ROLE_PUBLIC, null, null);
                    showToast("✓ Verified! Entered Citizen Portal.");
                    startActivity(new Intent(LoginActivity.this, MainActivity.class));
                    finish();
                } else {
                    showError("Invalid or expired OTP. Please try again.");
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                btnVerifyOtp.setEnabled(true);
                btnVerifyOtp.setText("Verify Details & Enter Dashboard →");
                String errorMsg = t.getMessage() != null ? t.getMessage() : "Unknown error";
                showError("Server Connection Failed: " + errorMsg);
                showServerChangeDialog();
            }
        });
    }

    private void updateServerStatusDisplay() {
        if (tvServerStatus == null) return;
        String current = preferenceManager.getServerUrl();
        ApiClient.setBaseUrl(current);
        if (current.equals(Constants.DEFAULT_BASE_URL) || current.contains("onrender.com") || current.contains("railway.app") || current.contains("koyeb.app") || current.contains("run.app")) {
            tvServerStatus.setText("🌐 Server: Cloud Live 24/7 (HTTPS)");
        } else if (current.contains("trycloudflare")) {
            tvServerStatus.setText("🌐 Server: Cloudflare Tunnel");
        } else if (current.contains("172.20.187.147")) {
            tvServerStatus.setText("🌐 Server: Local Wi-Fi (LAN)");
        } else if (current.contains("10.0.2.2")) {
            tvServerStatus.setText("🌐 Server: Emulator Loopback");
        } else {
            tvServerStatus.setText("🌐 Server: " + current);
        }
    }

    private void showServerChangeDialog() {
        String current = preferenceManager.getServerUrl();
        final EditText etCustomUrl = new EditText(this);
        etCustomUrl.setHint("e.g. https://your-server.com/api/");
        etCustomUrl.setText(current);
        etCustomUrl.setPadding(32, 24, 32, 24);

        final String[] options = {
            "Permanent Cloud Server (HTTPS)",
            "Local Wi-Fi LAN (172.20.187.147:8000)",
            "Android Emulator (10.0.2.2:8000)",
            "Custom URL (entered below)"
        };

        new androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("🌐 Backend Server Connection")
            .setMessage("Select backend connection mode or enter custom host IP/URL:")
            .setSingleChoiceItems(options, -1, (dialog, which) -> {
                switch (which) {
                    case 0:
                        etCustomUrl.setText(Constants.DEFAULT_BASE_URL);
                        break;
                    case 1:
                        etCustomUrl.setText(Constants.LAN_BASE_URL);
                        break;
                    case 2:
                        etCustomUrl.setText(Constants.EMULATOR_BASE_URL);
                        break;
                }
            })
            .setView(etCustomUrl)
            .setPositiveButton("Save & Connect", (dialog, which) -> {
                String inputUrl = etCustomUrl.getText().toString().trim();
                if (!inputUrl.isEmpty()) {
                    preferenceManager.setServerUrl(inputUrl);
                    updateServerStatusDisplay();
                    testServerHealth(inputUrl);
                }
            })
            .setNegativeButton("Cancel", null)
            .show();
    }

    private void testServerHealth(String url) {
        ApiClient.setBaseUrl(url);
        ApiClient.getApiService().getHealth().enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful()) {
                    showToast("✓ Connected to server successfully (200 OK)!");
                } else {
                    showError("Server responded with HTTP " + response.code());
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                showError("Could not reach " + url + ": " + t.getMessage());
            }
        });
    }
}
