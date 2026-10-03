package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
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

    // Quick 1-tap direct login buttons
    private Button btnRoleMasterAdmin;
    private Button btnRoleInspector;
    private Button btnRoleDistrict;
    private Button btnRoleState;
    private Button btnRoleMosje;
    private Button btnRoleContractor;
    private Button btnRoleNgo;
    private Button btnRolePublic;

    // Server configuration
    private TextView tvServerStatus;
    private Button btnChangeServer;

    private static final String[] POSITIONS = {
        "▼ Select Position to Enter Directly...",
        "1. 👑 Master Admin (Supreme Authority)",
        "2. 🔍 Field Inspector (Rajesh Meshram)",
        "3. 🏛️ District Officer (Virendra Deshmukh)",
        "4. 🏛️ State Officer (K. S. Patil)",
        "5. 🇮🇳 MoSJE Admin (Central Directorate)",
        "6. 🏢 Contractor (Larsen & Infra Partner)",
        "7. 🤝 NGO Auditor (Sewa Bharati Trust)",
        "8. 👤 Public Citizen (Masked Privacy Portal)"
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

        btnRoleMasterAdmin = findViewById(R.id.btnRoleMasterAdmin);
        btnRoleInspector = findViewById(R.id.btnRoleInspector);
        btnRoleDistrict = findViewById(R.id.btnRoleDistrict);
        btnRoleState = findViewById(R.id.btnRoleState);
        btnRoleMosje = findViewById(R.id.btnRoleMosje);
        btnRoleContractor = findViewById(R.id.btnRoleContractor);
        btnRoleNgo = findViewById(R.id.btnRoleNgo);
        btnRolePublic = findViewById(R.id.btnRolePublic);

        tvServerStatus = findViewById(R.id.tvServerStatus);
        btnChangeServer = findViewById(R.id.btnChangeServer);
        updateServerStatusDisplay();

        if (btnChangeServer != null) {
            btnChangeServer.setOnClickListener(v -> showServerChangeDialog());
        }
    }

    private void setupPositionDropdown() {
        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, POSITIONS);
        spnPosition.setAdapter(adapter);

        spnPosition.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                if (position > 0) {
                    directLoginPosition(position - 1);
                }
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }

    private void setupEvents() {
        if (btnRoleMasterAdmin != null) btnRoleMasterAdmin.setOnClickListener(v -> directLoginPosition(0));
        if (btnRoleInspector != null) btnRoleInspector.setOnClickListener(v -> directLoginPosition(1));
        if (btnRoleDistrict != null) btnRoleDistrict.setOnClickListener(v -> directLoginPosition(2));
        if (btnRoleState != null) btnRoleState.setOnClickListener(v -> directLoginPosition(3));
        if (btnRoleMosje != null) btnRoleMosje.setOnClickListener(v -> directLoginPosition(4));
        if (btnRoleContractor != null) btnRoleContractor.setOnClickListener(v -> directLoginPosition(5));
        if (btnRoleNgo != null) btnRoleNgo.setOnClickListener(v -> directLoginPosition(6));
        if (btnRolePublic != null) btnRolePublic.setOnClickListener(v -> directLoginPosition(7));
    }

    /**
     * Instantly logs into the application without credential forms, passwords, or security prompts.
     */
    private void directLoginPosition(int roleIndex) {
        int userId;
        String name;
        String role;
        Integer districtId;
        Integer stateId;
        String email;

        switch (roleIndex) {
            case 0: // Master Admin
                userId = 1;
                name = "Master Administrator";
                role = Constants.ROLE_MASTER_ADMIN;
                districtId = null;
                stateId = null;
                email = "admin@master.gov.in";
                break;
            case 1: // Field Inspector
                userId = 14;
                name = "Rajesh Meshram";
                role = Constants.ROLE_INSPECTOR;
                districtId = 1;
                stateId = 1;
                email = "inspector.rajesh@mosje.gov.in";
                break;
            case 2: // District Officer
                userId = 8;
                name = "Virendra Deshmukh";
                role = Constants.ROLE_DISTRICT;
                districtId = 1;
                stateId = 1;
                email = "district.nagpur@mosje.gov.in";
                break;
            case 3: // State Officer
                userId = 5;
                name = "K. S. Patil";
                role = Constants.ROLE_STATE;
                districtId = null;
                stateId = 1;
                email = "state.mh@mosje.gov.in";
                break;
            case 4: // MoSJE Admin
                userId = 2;
                name = "Central MoSJE Admin";
                role = Constants.ROLE_MOSJE_ADMIN;
                districtId = null;
                stateId = null;
                email = "mosje.admin@gov.in";
                break;
            case 5: // Contractor
                userId = 72;
                name = "Larsen & Infra Partner";
                role = Constants.ROLE_CONTRACTOR;
                districtId = 1;
                stateId = 1;
                email = "contractor@larseninfra.com";
                break;
            case 6: // NGO
                userId = 19;
                name = "Sewa Bharati Trust";
                role = Constants.ROLE_NGO;
                districtId = 1;
                stateId = 1;
                email = "demo.ngo@example.org";
                break;
            case 7: // Public Citizen
            default:
                userId = 99;
                name = "Public Citizen";
                role = Constants.ROLE_PUBLIC;
                districtId = null;
                stateId = null;
                email = "9821004567";
                break;
        }

        // 1. Immediately create session so user enters app with zero delay
        String directToken = "direct-" + role.toLowerCase() + "-" + System.currentTimeMillis();
        preferenceManager.saveSession(directToken, userId, name, role, districtId, stateId);

        // 2. Asynchronously synchronize genuine JWT backend token in background
        syncBackendTokenAsync(email, role);

        showToast("✓ Direct Access: " + name + " (" + role + ")");

        // 3. Immediately launch corresponding destination activity
        if (Constants.ROLE_CONTRACTOR.equalsIgnoreCase(role)) {
            startActivity(new Intent(LoginActivity.this, ContractorDashboardActivity.class));
        } else {
            startActivity(new Intent(LoginActivity.this, MainActivity.class));
        }
        finish();
    }

    private void syncBackendTokenAsync(String email, String role) {
        if (Constants.ROLE_PUBLIC.equalsIgnoreCase(role)) {
            return;
        }

        Map<String, String> credentials = new HashMap<>();
        credentials.put("email", email);
        credentials.put("username", email.contains("@") ? email : "admin");
        credentials.put("password", "demo@123");
        credentials.put("authenticator_code", "123456");

        ApiClient.getApiService().login(credentials).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    if (Boolean.TRUE.equals(body.get("success"))) {
                        Map<String, Object> data = (Map<String, Object>) body.get("data");
                        if (data != null && data.containsKey("token")) {
                            String realToken = (String) data.get("token");
                            preferenceManager.saveToken(realToken);
                        }
                    }
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                // Background sync error ignored, active direct session remains valid
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
