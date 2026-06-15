import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Twilio 10DLC" };

export default function Twilio10DLC() {
  return (
    <DocsLayout
      current="/docs/twilio-10dlc"
      title="Twilio 10DLC"
      description="Brand and campaign registration for US long-code traffic. Carriers throttle and eventually block unregistered conversational traffic. Start the registration before the demo depends on the number — turnaround is days, not hours."
    >
      <h2>What 10DLC is, briefly</h2>
      <p>
        10DLC stands for 10-digit long code — a regular US phone number used
        for application-to-person messaging. US carriers require any A2P
        traffic over a long code to be associated with a registered brand and a
        registered campaign. Unregistered traffic is throttled to a few
        messages per second, deprioritized, and often filtered as spam.
      </p>

      <h2>The three things to register</h2>
      <ul>
        <li>
          <strong>Brand</strong> — the legal entity that owns the messaging
          program. For a sole-proprietor service business this is the
          owner&rsquo;s legal name and EIN or SSN. For a registered LLC it is
          the company.
        </li>
        <li>
          <strong>Campaign</strong> — the purpose of the messaging. Foyer
          uses the <code>CONVERSATIONAL</code> use case — the customer texts
          first, the agent replies, the conversation is bidirectional and not
          marketing.
        </li>
        <li>
          <strong>Number association</strong> — link the Twilio number to the
          campaign so traffic on that number rides under the registered
          program.
        </li>
      </ul>

      <h2>Step-by-step</h2>
      <h3>1. Create the brand in Twilio Console</h3>
      <p>
        Console → Trust Hub → Customer Profiles → A2P Messaging Brand
        Registration. Fill in the legal entity details, the EIN or other tax
        ID, and the public website. The website must be live and reachable —
        Twilio&rsquo;s reviewer will check it. For a service business with a
        bare-bones site, link the homepage and the privacy policy.
      </p>

      <h3>2. Create the campaign</h3>
      <p>Use case: <code>CONVERSATIONAL</code>. Sample messages should reflect what the agent actually sends — for Anchor Plumbing:</p>
      <ul>
        <li>
          &ldquo;Hi, this is the booking line for Anchor Plumbing. I can help
          with that. What&rsquo;s the service address?&rdquo;
        </li>
        <li>
          &ldquo;Confirmed — Sam will be there Thursday 6/19 at 10am at 1432
          Oak St. Reply STOP to opt out of any future texts from this
          number.&rdquo;
        </li>
        <li>
          &ldquo;The slot is no longer available — want me to check another
          time?&rdquo;
        </li>
      </ul>
      <p>
        Confirm that STOP, HELP, and START opt-in language is present somewhere
        the customer sees it — the public site, the booking confirmation
        message, or both.
      </p>

      <h3>3. Associate the number</h3>
      <p>
        Messaging Services → your messaging service → Sender Pool → add the
        Twilio number. Then attach the registered campaign to the messaging
        service. Foyer reads the messaging service SID, not the number SID, for
        outbound sends — this is what lets carriers tie traffic back to the
        registered campaign.
      </p>

      <h3>4. Wait</h3>
      <p>
        Brand review is typically same-day to one business day. Campaign
        review is one to three business days. Number provisioning into the
        campaign is near-instant once the campaign is approved. Plan the demo
        timeline around the long pole — campaign review.
      </p>

      <h2>While you wait</h2>
      <p>
        Toll-free numbers can carry conversational traffic without 10DLC
        registration, at a different per-segment price. Twilio sandbox numbers
        work for local end-to-end testing. Either is fine for development —
        just keep the production-number path on the registration track in
        parallel.
      </p>

      <h2>If the campaign is rejected</h2>
      <p>
        The two recurring reasons are missing STOP/HELP/START language on the
        public site and sample messages that read as marketing rather than
        conversational. Update both, resubmit. Foyer&rsquo;s default
        confirmation message and welcome message are written to pass the
        conversational filter on first review — if you change them, run them
        past the same bar.
      </p>
    </DocsLayout>
  );
}
