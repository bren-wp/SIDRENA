namespace Sidrena.Core;

/// <summary>
/// Pure layout geometry policy tested without WPF. Values are device-independent
/// pixels (DIP), so the same rules apply at Windows 100–200% DPI.
/// </summary>
public static class ResponsiveLayoutPolicy
{
    public const double CompactSidebarBreakpoint = 1050;
    public const double HideHeroBadgeBreakpoint = 1160;
    public const double VerticalSplitBreakpoint = 800;

    public static DesktopWindowSize Initial(double workWidth,double workHeight)
    {
        if(!double.IsFinite(workWidth)||!double.IsFinite(workHeight)||
           workWidth<=0||workHeight<=0)
            throw new ArgumentOutOfRangeException(nameof(workWidth),"Radna površina monitora nije valjana.");
        // Always leave visible space around the window. Do not start maximized.
        var width=Math.Min(1320,workWidth*0.88);
        var height=Math.Min(820,workHeight*0.88);
        return new DesktopWindowSize(width,height);
    }

    public static DesktopLayoutMode ForWidth(double windowWidth)
    {
        if(!double.IsFinite(windowWidth)||windowWidth<=0)
            throw new ArgumentOutOfRangeException(nameof(windowWidth));
        bool compact=windowWidth<CompactSidebarBreakpoint;
        return new DesktopLayoutMode(
            SidebarWidth: compact?75:246,
            CompactNavigation:compact,
            ShowHeroBadge:windowWidth>=HideHeroBadgeBreakpoint,
            HeaderFontSize:windowWidth<840?25:windowWidth<1160?29:34,
            CompactContent:windowWidth<900);
    }

    public static bool StackDetails(double availableWidth)
        => double.IsFinite(availableWidth)&&availableWidth<VerticalSplitBreakpoint;
}
public sealed record DesktopWindowSize(double Width,double Height);
public sealed record DesktopLayoutMode(double SidebarWidth,bool CompactNavigation,
    bool ShowHeroBadge,double HeaderFontSize,bool CompactContent);
