namespace Payroll.Mobile.Models.Dto;

public sealed class LoginRequest
{
    public string EmpId { get; set; } = string.Empty;

    public string Password { get; set; } = string.Empty;
}

public sealed class LoginResponse
{
    public string Token { get; set; } = string.Empty;

    public string TokenType { get; set; } = "Bearer";

    public MobileUserDto User { get; set; } = new();
}

public sealed class MeResponse
{
    public MobileUserDto User { get; set; } = new();
}

public sealed class MobileUserDto
{
    public string EmpId { get; set; } = string.Empty;

    public string? Username { get; set; }

    public string? Firstname { get; set; }

    public string? Lastname { get; set; }

    public string? FullName { get; set; }

    public string? Position { get; set; }

    public string? Department { get; set; }

    public bool MustUpdateProfile { get; set; }
}

public sealed class MessageResponse
{
    public string Message { get; set; } = string.Empty;
}

public sealed class ClockStatusResponse
{
    public string Today { get; set; } = string.Empty;

    public bool CanTimeIn { get; set; }

    public bool CanTimeOut { get; set; }

    public bool OpenPreviousDay { get; set; }

    public DtrDto? Dtr { get; set; }
}

public sealed class DtrDto
{
    public int DtrId { get; set; }

    public string? DtrDate { get; set; }

    public string? TimeIn { get; set; }

    public string? TimeOut { get; set; }

    public string? TimeoutNextday { get; set; }
}

public sealed class ClockPunchRequest
{
    public string Punch { get; set; } = string.Empty;

    public double Latitude { get; set; }

    public double Longitude { get; set; }

    public string DeviceTimestamp { get; set; } = string.Empty;
}

public sealed class ClockPunchResponse
{
    public string Message { get; set; } = string.Empty;

    public DtrDto? Dtr { get; set; }
}

public sealed class DataResponse<T>
{
    public List<T> Data { get; set; } = [];
}

public sealed class LeaveTypeDto
{
    public int LeaveTypeId { get; set; }

    public string? LeaveName { get; set; }

    public string? Description { get; set; }

    public double? MaxValue { get; set; }
}

public sealed class LeaveDto
{
    public int LeaveId { get; set; }

    public int LeaveType { get; set; }

    public string? LeaveTypeName { get; set; }

    public int? Status { get; set; }

    public string? StatusName { get; set; }

    public string? StatusKey { get; set; }

    public bool IsPending { get; set; }

    public bool IsApproved { get; set; }

    public string? FilingDate { get; set; }

    public string? StartDate { get; set; }

    public string? EndDate { get; set; }

    public double? DaysWpay { get; set; }

    public double? DaysWopay { get; set; }

    public string? ApplicantNote { get; set; }

    public List<string> SelectedDates { get; set; } = [];
}

public sealed class LeaveCreateRequest
{
    public int LeaveType { get; set; }

    public string DateMode { get; set; } = "weekdays";

    public string? StartDate { get; set; }

    public string? EndDate { get; set; }

    public string? SelectedDates { get; set; }

    public bool AutoSplitCredits { get; set; } = true;

    public string? ApplicantNote { get; set; }
}

public sealed class LeaveCreatedResponse
{
    public string Message { get; set; } = string.Empty;

    public LeaveDto Leave { get; set; } = new();
}

public class PayslipListItemDto
{
    public int Id { get; set; }

    public string? PayrollPeriod { get; set; }

    public string? PayrollType { get; set; }

    public decimal? Gross { get; set; }

    public decimal? Net { get; set; }

    public decimal? Fifteenth { get; set; }

    public decimal? Thirtieth { get; set; }

    public string? SnapshotCreatedAt { get; set; }

    public string? PrintUrl { get; set; }
}

public sealed class PayslipDetailResponse
{
    public PayslipDetailDto Payslip { get; set; } = new();
}

public class PayslipDetailDto : PayslipListItemDto
{
    public Dictionary<string, object?> Employee { get; set; } = [];

    public List<PayslipLineDto> Earnings { get; set; } = [];

    public List<PayslipLineDto> StatutoryDeductions { get; set; } = [];

    public List<PayslipLineDto> ProgramDeductions { get; set; } = [];

    public List<PayslipLineDto> AdditionalPremiums { get; set; } = [];

    public List<PayslipLineDto> LoanDeductions { get; set; } = [];

    public Dictionary<string, object?> Tax { get; set; } = [];

    public Dictionary<string, object?> Totals { get; set; } = [];
}

public sealed class PayslipLineDto
{
    public string? Label { get; set; }

    public decimal? Amount { get; set; }
}
