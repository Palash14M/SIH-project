package com.chakravyuh.smartinspection.ui;

import android.content.Intent;
import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import androidx.appcompat.app.AppCompatActivity;

import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.util.PrefManager;

public class PublicTenderExplorerActivity extends AppCompatActivity {
    private PrefManager prefManager;
    private EditText etSearch;
    private LinearLayout layoutFilterChips;
    private LinearLayout layoutTenderList;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefManager = new PrefManager(this);

        initViews();
        loadPublicTenders();
    }

    private void initViews() {
        setContentView(R.layout.activity_tender_detail);

        etSearch = new EditText(this);
        etSearch.setHint("Search public works by tender number, title or district...");

        layoutTenderList = new LinearLayout(this);
        layoutTenderList.setOrientation(LinearLayout.VERTICAL);
    }

    private void loadPublicTenders() {
        // Calls GET /api/tenders
        // Server strictly filters out contractor phone/email/address and inspector identities/contacts.
        // For each tender, renders public summary card with:
        // - Tender number, title, category, sanctioned amount
        // - Contractor Name ONLY (Contractor Contact: RESTRICTED UNDER MOSJE POLICY)
        // - Progress %, Spent %, Variance %, Status
        // - Button "Nudge NGO" -> opens PublicNudgeActivity
    }

    private void openNudgeScreen(int tenderId, String tenderTitle, String ngoName) {
        Intent intent = new Intent(this, PublicNudgeActivity.class);
        intent.putExtra("TENDER_ID", tenderId);
        intent.putExtra("TENDER_TITLE", tenderTitle);
        intent.putExtra("NGO_NAME", ngoName);
        startActivity(intent);
    }
}
