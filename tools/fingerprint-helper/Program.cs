using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net;
using System.Text;
using System.Threading;
using System.Web.Script.Serialization;
using System.Xml.Linq;
using DPUruNet;

internal static class Program
{
    private const string Prefix = "http://127.0.0.1:52180/";
    private static readonly JavaScriptSerializer Json = new JavaScriptSerializer();
    private static readonly SemaphoreSlim CaptureLock = new SemaphoreSlim(1, 1);
    private static readonly HashSet<string> AllowedOrigins = new HashSet<string>(
        (Environment.GetEnvironmentVariable("MMMHMC_HRIS_ORIGINS") ?? "http://payroll-api.test")
            .Split(new[] { ',' }, StringSplitOptions.RemoveEmptyEntries).Select(x => x.Trim()),
        StringComparer.OrdinalIgnoreCase);

    public static void Main()
    {
        using (var listener = new HttpListener()) {
            listener.Prefixes.Add(Prefix);
            listener.Start();
            Console.WriteLine("MMMHMC U.are.U helper listening on " + Prefix);
            while (true) ThreadPool.QueueUserWorkItem(Handle, listener.GetContext());
        }
    }

    private static void Handle(object state)
    {
        var context = (HttpListenerContext)state;
        try {
            var origin = context.Request.Headers["Origin"] ?? "";
            if (!AllowedOrigins.Contains(origin)) { Write(context, 403, new { message = "Origin is not allowed." }, origin); return; }
            if (context.Request.HttpMethod == "OPTIONS") { Write(context, 204, null, origin); return; }
            if (context.Request.HttpMethod == "GET" && context.Request.Url.AbsolutePath == "/health") { Write(context, 200, ReaderHealth(), origin); return; }
            if (context.Request.HttpMethod == "POST" && context.Request.Url.AbsolutePath == "/enroll") { Enroll(context, origin); return; }
            Write(context, 404, new { message = "Not found." }, origin);
        } catch (Exception ex) { Write(context, 500, new { message = ex.Message }, context.Request.Headers["Origin"] ?? ""); }
    }

    private static object ReaderHealth()
    {
        try {
            using (var readers = ReaderCollection.GetReaders()) {
                var detected = readers.Count > 0;
                var models = new List<string>();
                for (var index = 0; index < readers.Count; index++) {
                    var name = readers[index].Description.Name;
                    if (!string.IsNullOrWhiteSpace(name)) models.Add(name);
                }

                return new {
                    helper_ready = true,
                    scanner_detected = detected,
                    scanner_count = readers.Count,
                    scanner_models = models.Distinct().ToArray(),
                    status = detected ? "ready" : "not_connected",
                    message = detected
                        ? "Fingerprint scanner detected and ready."
                        : "No compatible DigitalPersona fingerprint scanner was detected."
                };
            }
        } catch (Exception ex) {
            return new {
                helper_ready = true,
                scanner_detected = false,
                scanner_count = 0,
                scanner_models = new string[0],
                status = "sdk_error",
                message = "The DigitalPersona runtime could not enumerate fingerprint scanners: " + ex.Message
            };
        }
    }

    private static void Enroll(HttpListenerContext context, string origin)
    {
        if (!CaptureLock.Wait(0)) { Write(context, 409, new { message = "Another capture is in progress." }, origin); return; }
        try {
            using (var readers = ReaderCollection.GetReaders()) {
                if (readers.Count == 0) { Write(context, 503, new { message = "U.are.U reader not found." }, origin); return; }
                var reader = readers[0];
                var opened = reader.Open(Constants.CapturePriority.DP_PRIORITY_COOPERATIVE);
                if (opened != Constants.ResultCode.DP_SUCCESS) { Write(context, 503, new { message = "The fingerprint reader is busy or unavailable." }, origin); return; }
                try {
                    var samples = new List<Fmd>();
                    DataResult<Fmd> enrolled = null;
                    for (var attempt = 0; attempt < 12; attempt++) {
                        var capture = reader.Capture(Constants.Formats.Fid.ANSI, Constants.CaptureProcessing.DP_IMG_PROC_DEFAULT, 5000, -1);
                        if (capture.ResultCode != Constants.ResultCode.DP_SUCCESS || capture.Quality != Constants.CaptureQuality.DP_QUALITY_GOOD || capture.Data == null) continue;
                        var feature = FeatureExtraction.CreateFmdFromFid(capture.Data, Constants.Formats.Fmd.DP_PRE_REGISTRATION);
                        if (feature.ResultCode != Constants.ResultCode.DP_SUCCESS || feature.Data == null) continue;
                        samples.Add(feature.Data);
                        enrolled = Enrollment.CreateEnrollmentFmd(Constants.Formats.Fmd.DP_REGISTRATION, samples);
                        if (enrolled.ResultCode == Constants.ResultCode.DP_SUCCESS) break;
                    }
                    if (enrolled == null || enrolled.ResultCode != Constants.ResultCode.DP_SUCCESS || enrolled.Data == null) { Write(context, 422, new { message = "Enrollment needs more good-quality scans. Please try again." }, origin); return; }
                    var bytes = ExtractTemplate(Fmd.SerializeXml(enrolled.Data));
                    if (bytes.Length != 1632) { Write(context, 422, new { message = "Captured template is not compatible with the configured 1,632-byte format." }, origin); return; }
                    Write(context, 200, new { template = Convert.ToBase64String(bytes), format = "DP_REGISTRATION", quality = 100, reader_model = reader.Description.Name ?? "DigitalPersona U.are.U 4500", reader_serial = reader.Description.SerialNumber }, origin);
                } finally { reader.Dispose(); }
            }
        } finally { CaptureLock.Release(); }
    }

    private static byte[] ExtractTemplate(string xml)
    {
        var candidates = XDocument.Parse(xml).Descendants().Select(x => x.Value.Trim()).Where(x => x.Length > 100);
        foreach (var value in candidates) try { var bytes = Convert.FromBase64String(value); if (bytes.Length > 100) return bytes; } catch (FormatException) { }
        throw new InvalidDataException("The SDK did not return a binary registration template.");
    }

    private static void Write(HttpListenerContext context, int status, object payload, string origin)
    {
        context.Response.StatusCode = status;
        if (AllowedOrigins.Contains(origin)) context.Response.Headers["Access-Control-Allow-Origin"] = origin;
        context.Response.Headers["Access-Control-Allow-Headers"] = "Content-Type";
        context.Response.Headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS";
        if (payload != null) { var bytes = Encoding.UTF8.GetBytes(Json.Serialize(payload)); context.Response.ContentType = "application/json"; context.Response.OutputStream.Write(bytes, 0, bytes.Length); }
        context.Response.Close();
    }
}
