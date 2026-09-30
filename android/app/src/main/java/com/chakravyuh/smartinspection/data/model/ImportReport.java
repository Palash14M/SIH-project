package com.chakravyuh.smartinspection.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class ImportReport {
    @SerializedName("total_rows")
    private int totalRows;

    @SerializedName("created_count")
    private int createdCount;

    @SerializedName("updated_count")
    private int updatedCount;

    @SerializedName("rejected_count")
    private int rejectedCount;

    @SerializedName("rejected_rows")
    private List<RejectedRow> rejectedRows;

    public static class RejectedRow {
        @SerializedName("row")
        private int row;

        @SerializedName("tender_number")
        private String tenderNumber;

        @SerializedName("reason")
        private String reason;

        public int getRow() { return row; }
        public String getTenderNumber() { return tenderNumber; }
        public String getReason() { return reason; }
    }

    public int getTotalRows() { return totalRows; }
    public int getCreatedCount() { return createdCount; }
    public int getUpdatedCount() { return updatedCount; }
    public int getRejectedCount() { return rejectedCount; }
    public List<RejectedRow> getRejectedRows() { return rejectedRows; }
}
