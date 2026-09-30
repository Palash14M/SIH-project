package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.EditText;
import android.widget.TextView;
import androidx.annotation.NonNull;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.util.Constants;
import com.google.android.material.bottomnavigation.BottomNavigationView;
import com.google.android.material.floatingactionbutton.FloatingActionButton;

import android.text.Editable;
import android.text.TextWatcher;
import androidx.fragment.app.Fragment;
import androidx.fragment.app.FragmentManager;

public class MainActivity extends BaseActivity {
    private TextView tvGreeting;
    private TextView tvRoleBadge;
    private EditText etSearchTender;
    private BottomNavigationView bottomNav;
    private FloatingActionButton fabCamera;

    private HomeFragment homeFragment;
    private InspectionsFragment inspectionsFragment;
    private NotificationsFragment notificationsFragment;
    private ProfileFragment profileFragment;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        initViews();
        setupUserInterface();
        setupNavigation();
        setupSearch();

        // Default to HomeFragment
        loadFragment(new HomeFragment());
    }

    private void initViews() {
        tvGreeting = findViewById(R.id.tvGreeting);
        tvRoleBadge = findViewById(R.id.tvRoleBadge);
        etSearchTender = findViewById(R.id.etSearchTender);
        bottomNav = findViewById(R.id.bottomNavigationView);
        fabCamera = findViewById(R.id.fabCamera);
    }

    private void setupUserInterface() {
        String role = preferenceManager.getUserRole();
        String name = preferenceManager.getUserName();

        tvGreeting.setText(String.format("Namaste, %s", name != null && !name.isEmpty() ? name : "Official"));
        tvRoleBadge.setText(formatRoleBadge(role));

        // The centered orange circular camera button is displayed for Inspectors
        if (Constants.ROLE_INSPECTOR.equalsIgnoreCase(role)) {
            fabCamera.setVisibility(View.VISIBLE);
            fabCamera.setOnClickListener(v -> openCameraActivity());
        } else {
            fabCamera.setVisibility(View.GONE);
        }
    }

    private String formatRoleBadge(String role) {
        if (Constants.ROLE_INSPECTOR.equalsIgnoreCase(role)) return "Field Inspector • Active Session";
        if (Constants.ROLE_DISTRICT_OFFICER.equalsIgnoreCase(role)) return "District Officer • Administration";
        if (Constants.ROLE_STATE_OFFICER.equalsIgnoreCase(role)) return "State Officer • Oversight";
        if (Constants.ROLE_MOSJE_ADMIN.equalsIgnoreCase(role)) return "MoSJE National Admin";
        if (Constants.ROLE_MASTER_ADMIN.equalsIgnoreCase(role)) return "Master Admin • Supreme Governance";
        if (Constants.ROLE_NGO.equalsIgnoreCase(role)) return "NGO / Social Auditor";
        if (Constants.ROLE_CONTRACTOR.equalsIgnoreCase(role)) return "Contractor / Agency Partner";
        return "Public Citizen Portal";
    }

    private void setupNavigation() {
        bottomNav.setOnItemSelectedListener(item -> {
            int itemId = item.getItemId();
            if (itemId == R.id.nav_home) {
                if (homeFragment == null) homeFragment = new HomeFragment();
                loadFragment(homeFragment);
                return true;
            } else if (itemId == R.id.nav_inspections) {
                if (inspectionsFragment == null) inspectionsFragment = new InspectionsFragment();
                loadFragment(inspectionsFragment);
                return true;
            } else if (itemId == R.id.nav_notifications) {
                if (notificationsFragment == null) notificationsFragment = new NotificationsFragment();
                loadFragment(notificationsFragment);
                return true;
            } else if (itemId == R.id.nav_profile) {
                if (profileFragment == null) profileFragment = new ProfileFragment();
                loadFragment(profileFragment);
                return true;
            }
            return false;
        });
    }

    private void setupSearch() {
        etSearchTender.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            @Override public void onTextChanged(CharSequence s, int start, int before, int count) {
                if (inspectionsFragment != null && inspectionsFragment.isVisible()) {
                    inspectionsFragment.filterBySearch(s.toString());
                }
            }
            @Override public void afterTextChanged(Editable s) {}
        });
    }

    private void loadFragment(Fragment fragment) {
        getSupportFragmentManager()
            .beginTransaction()
            .replace(R.id.fragmentContainer, fragment)
            .commit();
    }

    public void switchToTab(int navId) {
        bottomNav.setSelectedItemId(navId);
    }

    public void openCamera() {
        openCameraActivity();
    }

    private void openCameraActivity() {
        Intent intent = new Intent(this, CameraActivity.class);
        startActivity(intent);
    }
}
