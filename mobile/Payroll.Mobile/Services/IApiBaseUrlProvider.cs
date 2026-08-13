namespace Payroll.Mobile.Services;

public interface IApiBaseUrlProvider
{
    string Current { get; }

    void Set(string url);
}
