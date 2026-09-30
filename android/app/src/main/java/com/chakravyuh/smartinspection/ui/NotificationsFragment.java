package com.chakravyuh.smartinspection.ui;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import com.chakravyuh.smartinspection.R;
import com.chakravyuh.smartinspection.network.ApiClient;
import java.util.List;
import java.util.Map;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class NotificationsFragment extends BaseFragment {
    private TextView tvNotificationHeader;
    private Button btnMarkAllRead;
    private TextView tvEmptyNotifs;
    private LinearLayout layoutNotifList;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View root = inflater.inflate(R.layout.fragment_notifications, container, false);
        initViews(root);
        setupEvents();
        loadNotifications();
        return root;
    }

    private void initViews(View v) {
        tvNotificationHeader = v.findViewById(R.id.tvNotificationHeader);
        btnMarkAllRead = v.findViewById(R.id.btnMarkAllRead);
        tvEmptyNotifs = v.findViewById(R.id.tvEmptyNotifs);
        layoutNotifList = v.findViewById(R.id.layoutNotifList);
    }

    private void setupEvents() {
        btnMarkAllRead.setOnClickListener(v -> markAllAsRead());
    }

    private void loadNotifications() {
        tvEmptyNotifs.setVisibility(View.VISIBLE);
        tvEmptyNotifs.setText("Checking official alerts...");

        ApiClient.getApiService().getNotifications().enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Map<String, Object> body = response.body();
                    List<Map<String, Object>> list = (List<Map<String, Object>>) body.get("data");
                    if (list != null && !list.isEmpty()) {
                        renderNotifications(list);
                        return;
                    }
                }
                tvEmptyNotifs.setText("No official notifications at this time.");
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {
                tvEmptyNotifs.setText("Failed to load notifications: " + t.getMessage());
            }
        });
    }

    private void renderNotifications(List<Map<String, Object>> notifs) {
        layoutNotifList.removeAllViews();
        tvEmptyNotifs.setVisibility(View.GONE);

        int unreadCount = 0;
        for (Map<String, Object> item : notifs) {
            View card = LayoutInflater.from(getContext()).inflate(R.layout.item_notification_card, layoutNotifList, false);

            View indicator = card.findViewById(R.id.indicatorUnread);
            TextView tvType = card.findViewById(R.id.tvNotifType);
            TextView tvTime = card.findViewById(R.id.tvNotifTime);
            TextView tvTitle = card.findViewById(R.id.tvNotifTitle);
            TextView tvMessage = card.findViewById(R.id.tvNotifMessage);

            Number idNum = (Number) item.get("id");
            final int notifId = idNum != null ? idNum.intValue() : 0;
            String type = (String) item.get("type");
            String title = (String) item.get("title");
            String message = (String) item.get("message");
            String time = (String) item.get("created_at");
            Boolean isRead = (Boolean) item.get("is_read");

            tvType.setText(type != null ? type : "ALERT");
            tvTitle.setText(title != null ? title : "Notification");
            tvMessage.setText(message != null ? message : "");
            tvTime.setText(time != null ? time : "Recent");

            if (Boolean.TRUE.equals(isRead)) {
                indicator.setVisibility(View.GONE);
            } else {
                indicator.setVisibility(View.VISIBLE);
                unreadCount++;
            }

            card.setOnClickListener(v -> {
                if (!Boolean.TRUE.equals(isRead) && notifId > 0) {
                    markSingleRead(notifId, indicator);
                }
            });

            layoutNotifList.addView(card);
        }

        tvNotificationHeader.setText(String.format("Notifications (%d unread)", unreadCount));
    }

    private void markSingleRead(int id, View indicator) {
        ApiClient.getApiService().markNotificationRead(id).enqueue(new Callback<Map<String, Object>>() {
            @Override
            public void onResponse(Call<Map<String, Object>> call, Response<Map<String, Object>> response) {
                if (response.isSuccessful()) {
                    indicator.setVisibility(View.GONE);
                }
            }

            @Override
            public void onFailure(Call<Map<String, Object>> call, Throwable t) {}
        });
    }

    private void markAllAsRead() {
        showToast("Marking notifications as read...");
        loadNotifications();
    }
}
