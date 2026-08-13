using Payroll.Mobile.Models.Dto;
using Refit;

namespace Payroll.Mobile.Services;

public interface IPayrollApi
{
    [Post("/api/mobile/auth/login")]
    Task<LoginResponse> LoginAsync([Body] LoginRequest request);

    [Post("/api/mobile/auth/logout")]
    Task<MessageResponse> LogoutAsync();

    [Get("/api/mobile/me")]
    Task<MeResponse> MeAsync();

    [Get("/api/mobile/clock/status")]
    Task<ClockStatusResponse> GetClockStatusAsync();

    [Post("/api/mobile/clock")]
    Task<ClockPunchResponse> PunchAsync([Body] ClockPunchRequest request);

    [Get("/api/mobile/leave-types")]
    Task<DataResponse<LeaveTypeDto>> GetLeaveTypesAsync();

    [Get("/api/mobile/leaves")]
    Task<DataResponse<LeaveDto>> GetLeavesAsync([Query] string status = "active");

    [Post("/api/mobile/leaves")]
    Task<LeaveCreatedResponse> CreateLeaveAsync([Body] LeaveCreateRequest request);

    [Get("/api/mobile/payslips")]
    Task<DataResponse<PayslipListItemDto>> GetPayslipsAsync();

    [Get("/api/mobile/payslips/{id}")]
    Task<PayslipDetailResponse> GetPayslipAsync(int id);
}
